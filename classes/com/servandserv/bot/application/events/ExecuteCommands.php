<?php

namespace com\servandserv\bot\application\events;

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
use \com\servandserv\data\bot\Commands;
use \com\servandserv\data\bot\Command;
use \com\servandserv\bot\domain\model\Dialog;

class ExecuteCommands extends AsyncLazy implements Subscriber
{

    protected $commands;
    protected $dialogs;

    public function __construct( $id = NULL, array $commands = NULL, array $dialogs = NULL )
    {
        if( is_array( $commands ) ) {
            $this->commands = $commands;
        }
        if( is_array( $dialogs ) ) {
            $this->dialogs = $dialogs;
        }
        parent::__construct( $id );
    }

    public function isSubscribedTo( Event $event )
    {
        // реагируем на событие регистрации апдейтов
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
            $params["commands"] = $this->commands;// доступные команды
            $params["dialogs"] = $this->dialogs;// доступные диалоги
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
            $this->addCommands( $args["commands"] );
            $this->addDialogs( $args["dialogs"] );
            
            /**
             * Читаем все новые апдейты
             * прогоняем их через команды
             * после прогона архивируем
             * повторяемся пока не прочитаем все в очереди
             */
            $rep = $sl->create( "com.servandserv.bot.domain.model.UpdateRepository" );
            $drep = $sl->create( "com.servandserv.bot.domain.model.DialogRepository" );
            $aiml = $sl->create( "com.servandserv.bot.domain.model.AIMLRepository" )->read();
            $fm = $sl->create( "com.servandserv.bot.domain.service.FuzzyMean" );
            $updates = $rep->findAllActive();
            
            while( count( $updates ) > 0 ) {
                foreach( $updates as $autoid=>$update ) {
                    try {
                        foreach( $this->commands as $className ) {
                            if( class_exists( $className ) && call_user_func_array( $className."::fit", [ $update, $this ] ) ) {
                                $this->execute( $className, $update, $sl, $pubsub );
                            }
                        }
                        if( $update->getMessage() && $update->getMessage()->getText() && $update->getCommand() === NULL ) {
                            // ищем в репе диалог, если не гаходим репа вернет нам новый
                            $dialog = $drep->findForChat( $update->getChat(), new Dialog() );
                            // получаем нормализованную строку вопроса
                            $normalized = $fm->normalize( $update->getMessage()->getText() );
                            // ищем ответ
                            $answer = $aiml->answer( $normalized, $dialog->getLastAnswer(), $this->getEnv( $update ), $dialog->varsToAssocArray() );
                            // создаем следующую итерацию диалога
                            $dialog->setInterchange( $dialog->createInterchange( $update->getMessage()->getText(), $answer ) );
                            // устанавливаем измененные переменные диалога
                            $dialog->varsFromAssocArray( $aiml->getVars() );
                            // обновим в базе
                            $drep->register( $dialog->setChat( $update->getChat() ) );
                            $update->setDialog( $dialog );
                            
                            // работаем с диалогами только тогда, когда известно, что ни одна команда не сработала
                            foreach( $this->dialogs as $className ) {
                                if( class_exists( $className ) && call_user_func_array( $className."::fit", [ $update, $this ] ) ) {
                                    $this->execute( $className, $update, $sl, $pubsub );
                                }
                            }
                            
                            // пытаемся отработать команды
                            $commands = array_unique( $aiml->getCommands() );
                            foreach( $commands as $command ) {
                                // сделаем фейковый апдейт чтобы проверить текст команды на соответствие шаблону
                                $up = ( new Update() )->setCommand( ( new Command() )->setName( $command ) );
                                // и заново побежали по командам
                                foreach( $this->commands as $className ) {
                                    if( class_exists( $className ) && call_user_func_array( $className."::fit", [ $up, $this ] ) ) {
                                        // нашли такую команду подставим ее в update
                                        $update->setCommand( $up->getCommand() );
                                        $this->execute( $className, $update, $sl, $pubsub );
                                    }
                                }
                            }
                        }
                        // не забываем очистить диалоги
                        $aiml->reset();
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
                        $pubsub->publish( new ExceptionOccuredEvent( new \Exception( "Error on update \"$autoid\" execute command\n".$update->getRaw(), 0, $e ) ) );
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
            $pubsub->publish( new ExceptionOccuredEvent( $e ) );
            //trigger_error( $e->getMessage()." in file ".$e->getFile()." on line ".$e->getLine() );
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
    
    private function addDialogs( array $dialogs )
    {
        $this->dialogs = $dialogs;
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

    private function getEnv( Update $up )
    {
        $env = [];
        $user = $up->getChat()->getUser()->getFirstName()." ".$up->getChat()->getUser()->getLastName();
        if( !$user ) $user = "***";
        $env["USER"] = $user;
        if( $up->getChat()->getContact() ) {
            $env["PHONE"] = "TRUE";
        }
        $env["CONTEXT"] = $up->getContext();

        return $env;
    }
}