<?php

namespace com\servandserv\bot\application\events;

use \com\servandserv\happymeal\errors\Error;
use \com\servandserv\data\bot\Update;
use \com\servandserv\bot\application\ServiceLocator;
use \com\servandserv\bot\domain\model\events\Subscriber;
use \com\servandserv\bot\domain\model\events\Publisher;
use \com\servandserv\bot\domain\model\events\Event;
use \com\servandserv\bot\domain\model\events\UserNotFoundOccuredEvent;
use \com\servandserv\bot\domain\model\UserNotFoundException;
use \com\servandserv\bot\domain\model\events\ErrorOccuredEvent;
use \com\servandserv\bot\infrastructure\AsyncLazy;
use \com\servandserv\data\bot\Commands;
use \com\servandserv\data\bot\Command;


class ExecuteCommands extends AsyncLazy implements Subscriber
{

    protected $commands;

    public function __construct( $id = NULL, array $commands = NULL )
    {
        if( is_array( $commands ) ) {
            $this->commands = $commands;
        }
        parent::__construct( $id );
    }

    public function isSubscribedTo( Event $event )
    {
        return ( $event instanceof \com\servandserv\bot\domain\model\events\UpdatesRegisteredEvent );
    }
    
    public function handle( Event $event )
    {
        /**
        $params["event"] = $event;
        $params["commands"] = $this->commands;
        $this->setBootstrap( "../conf/conf.php" );
        $this->fork( $params );
        */
        
        /**
         *
         * Запускаем один раз и работаем пока не выполнили всю работу
         * для этого создаем блокировку которую проверяем при следующем заходе
         * если работа еще не закончена, то блокировка  снята, проходим мимо
         * если блокировки нет снова запускаем процесс
         *
         */
        $lockfile = sys_get_temp_dir() . "/" . str_replace("/", "_", __FILE__) . ".lock";
        //if (file_exists($lockfile)) {
        //    sleep(5);
        //}
        $lockfp = fopen( $lockfile, "w" );
        if ( !flock( $lockfp, LOCK_EX | LOCK_NB ) ) {
            // работа все еще выполняется
            fclose( $lockfp );
        } else {
            // похоже никакой активной работы нет пытаемся запустить ее выполнение
            $params["lockfile"] = $lockfile;
            $params["event"] = $event;
            $params["commands"] = $this->commands;
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
                trigger_error( "lock file $lockfile error in " . __FILE__ . " on " . __LINE__ );
            }
            
            $sl = \Locator::getInstance();
            $pubsub = $args["event"]->getPubSub();
            $this->addCommands( $args["commands"] );
            
            /**
             * Читаем все новые апдейты
             * прогоняем их через команды
             * после прогона архивируем
             * повторяемся пока не прочитаем все в очереди
             */
            $rep = $sl->create( "com.servandserv.bot.domain.model.UpdateRepository" );
            //$sf = $sl->create( "com.servandserv.bot.domain.service.SentenceFactory" );
            $updates = $rep->findAllActive();
            
            while( count( $updates ) > 0 ) {
                foreach( $updates as $autoid=>$update ) {
                    try {
                        /**
                        if( $update->getMessage() && $update->getMessage()->getText() ) {
                            $s = $sf->create( $update->getMessage()->getText() );
                            $update->setSentence( $s );
                        }
                        */
                        foreach( $this->commands as $className ) {
                            if( class_exists( $className ) && call_user_func_array( $className."::fit", [ $update, $this ] ) ) {
                                $this->execute( $className, $update, $sl, $pubsub );
                            }
                        }
                        // фиксируем, что обработан
                        $update->setStatus( $rep::EXECUTED );
                    } catch( \UserNotFoundException $e ) {
                        // не нашли пользователя
                        // надо сообщить об этом
                        $update->setStatus( $rep::POSTPONED );
                        $pubsub->publish( new UserNotFoundOccuredEvent( $update->getChat() ) );
                    } catch( \Exception $e ) {
                        // случилась не понятная нам ошибка
                        // уведомим админа
                        $update->setStatus( $rep::POSTPONED );
                        $pubsub->publish( new ErrorOccuredEvent( ( new Error() )
                            ->setDescription( "Error on update \"$autoid\" execute command with message ".$e->getCode().": ".$e->getMessage() ) 
                        ));
                    }
                    // поместим в архив
                    $rep->archive( $autoid, $update );
                }
                // посмотрим может еще кто-то прилетел
                $updates = $rep->findAllActive();
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
            trigger_error( $e->getMessage()." in file ".$e->getFile()." on line ".$e->getLine() );
        }
    }
    
    private function execute( $className, Update $update, ServiceLocator $sl, Publisher $pubsub )
    {
        $port = $sl->create( "com.servandserv.bot.domain.model.BotPort", [ $update->getContext() ] );
        $cl = new \ReflectionClass( $className );
        $obj = call_user_func_array( array( &$cl, 'newInstance' ), [ $pubsub ] );
        $obj->execute( $update, $sl, $this->toCommandsDTO(), $port );
    }
    
    private function addCommands( array $commands )
    {
        $this->commands = $commands;
        return $this;
    }
    
    public function toCommandsDTO()
    {
        $commands = new Commands();;
        foreach( $this->commands as $clName ) {
            if( $clName::$name !== NULL ) {
                $command = new Command();
                $command->setComments( $clName::$name )->setName( $clName::$command );
                $commands->setCommand( $command );
            }
        }
        return $commands;
    }
}