<?php

namespace com\servandserv\bot\domain\model\fb;

use \com\servandserv\happymeal\errors\Error;
use \com\servandserv\happymeal\errors\Errors;
use \com\servandserv\data\bot\Update;
use \com\servandserv\bot\application\ServiceLocator;
use \com\servandserv\bot\domain\model\events\Subscriber;
use \com\servandserv\bot\domain\model\events\Publisher;
use \com\servandserv\bot\domain\model\events\Event;
use \com\servandserv\bot\domain\model\events\UserNotFoundOccuredEvent;
use \com\servandserv\bot\domain\model\UserNotFoundException;
//use \com\servandserv\bot\domain\model\events\ErrorOccuredEvent;
use \com\servandserv\bot\domain\model\events\ExceptionOccuredEvent;
use \com\servandserv\bot\infrastructure\AsyncLazy;

/**
 * сервис обновления профилей пользователей facebook
 * при получении апдейта смотрим чаты которые есть в этом update
 * находим те из них по которым нет данных о полльзователях
 * запрашиваем инофрмацию и сохраняем ее в базе
 */
class UserProfileService extends AsyncLazy implements Subscriber
{

    public function __construct( $id = NULL )
    {
        parent::__construct( $id );
    }

    public function isSubscribedTo( Event $event )
    {
        // реагируем на событие регистрации апдейтов
        return $event instanceof \com\servandserv\bot\domain\model\events\UpdatesRegisteredEvent &&
            $event->getUpdates()->getContext() === \com\servandserv\data\bot\ContextType::_COM_FACEBOOK;
    }
    
    public function handle( Event $event )
    {
        $lockfile = sys_get_temp_dir() . "/" . str_replace("/", "_", __FILE__) . ".lock";
        //if ( file_exists( $lockfile ) ) {
        //    sleep( 5 );
        //}
        $lockfp = fopen( $lockfile, "w" );
        if ( !flock( $lockfp, LOCK_EX | LOCK_NB ) ) {
            // работа все еще выполняется
            fclose( $lockfp );
        } else {
            // похоже никакой активной работы нет пытаемся запустить ее выполнение
            $params["lockfile"] = $lockfile;
            $params["event"] = $event;
            $this->setBootstrap( "../conf/conf.php" );
            $this->fork( $params );

            flock( $lockfp, LOCK_UN );
            fclose( $lockfp );
        }
    }
    
    public function run( $args )
    {
        try {
            
            $lockfile = $args["lockfile"];
            $lockfp = fopen( $lockfile, "w" );
            $start = time();
            //ждем пока нам освободит блокировку вызывавший скрипт (10 сек)
            while ( !flock( $lockfp, LOCK_EX | LOCK_NB ) && time() - $start < 10 ) {

            }

            //пытаемся заблокировать сами
            if ( !flock( $lockfp, LOCK_EX | LOCK_NB ) ) {
                fclose( $lockfp );
                // можем упасть, следующий апдейт снова активирует скрипт
                trigger_error( "lock file $lockfile error in " . __FILE__ . " on " . __LINE__ );
            }
            
            $sl = \Locator::getInstance();
            $pubsub = $args["event"]->getPubSub();
            
            /**
             * Читаем все новые апдейты
             * прогоняем их через команды
             * после прогона архивируем
             * повторяемся пока не прочитаем все в очереди
             */
            $rep = $sl->create( "com.servandserv.bot.domain.model.ChatRepository" );
            $updates = $args["event"]->getUpdates()->getUpdate();
            
            foreach( $updates as $update ) {
                try {
                    $chat = $rep->findByKeys( [ $update->getChat()->getId(), $update->getContext() ] );
                    if( !$chat->getUser()->getFirstName() && !$chat->getUser()->getLastName() ) {
                        $port = $sl->create( "com.servandserv.bot.domain.model.UserProfilePort", [ $update->getContext() ] );
                        $user = $port->findUser( $chat );
                        $chat->setUser( $user );
                        $rep->updateChatUser( $chat );
                    }
                } catch( \Exception $e ) {
                    trigger_error( $e->getMessage() );
                }
            }
            
            // и закроем асинхронную часть
            $this->setResult( TRUE );
            $this->close();
        } catch( \Exception $e ) {
            // что-то не задалось
            // закроем асинхронную часть
            // глобальную блокировку говорят отпустит само
            $this->setResult( TRUE );
            $this->close();
            $pubsub->publish( new ExceptionOccuredEvent( $e ) );
            //trigger_error( $e->getMessage()." in file ".$e->getFile()." on line ".$e->getLine() );
        }
    }
}