<?php

namespace com\servandserv\Bot\Application\Events;

use \com\servandserv\happymeal\errors\Error;
use \com\servandserv\data\bot\Update;
use \com\servandserv\Bot\Application\ServiceLocator;
use \com\servandserv\Bot\Domain\Model\Events\Subscriber;
use \com\servandserv\Bot\Domain\Model\Events\Publisher;
use \com\servandserv\Bot\Domain\Model\Events\Event;
use \com\servandserv\Bot\Domain\Model\Events\UserNotFoundOccuredEvent;
use \com\servandserv\Bot\Domain\Model\Events\ErrorOccuredEvent;
use \com\servandserv\Bot\Infrastructure\AsyncLazy;
use \com\servandserv\data\bot\Commands;
use \com\servandserv\data\bot\Command;


class ExecuteCommands extends AsyncLazy implements Subscriber
{

    protected $commands;
    protected $duration;

    public function __construct( $id = NULL, array $commands = NULL, $duration = NULL )
    {
        if( is_array( $commands ) ) {
            $this->commands = $commands;
        }
        $this->duration = $duration;
        parent::__construct( $id );
    }

    public function isSubscribedTo( Event $event )
    {
        return is_a( $event, 'com\servandserv\Bot\Domain\Model\Events\UpdateRegisteredEvent' ) ? TRUE : FALSE;
    }
    
    public function handle( Event $event )
    {
        $params["event"] = $event;
        $params["commands"] = $this->commands;
        $this->setBootstrap( "../conf/conf.php" );
        $this->fork( $params );
    }
    
    public function run( $args )
    {
        $pubsub = $args["event"]->getPubSub();
        try {
            
            $sl = \Locator::getInstance();
        
            $this->addCommands( $args["commands"] );
            $update = $args["event"]->getUpdate();
            foreach( $this->commands as $className ) {
                if( class_exists( $className ) && call_user_func_array( $className."::fit", [ $update, $this ] ) ) {
                    $this->execute( $className, $update, $sl, $pubsub );
                }
            }
            
            // и закроем асинхронную часть
            $this->setResult( TRUE );
            $this->close();
        } catch( \UserNotFoundException $e ) {
            $this->setResult( TRUE );
            $this->close();
            $pubsub->publish( new UserNotFoundOccuredEvent( $update->getChat() ) );
            throw new \Exception( $e->getMessage(), $e->getCode() );
        } catch( \Exception $e ) {
            // что-то не задалось
            // закроем асинхронную часть
            // глобальную блокировку говорят отпустит само
            $this->setResult( TRUE );
            $this->close();
            throw new \Exception( $e->getMessage(), $e->getCode() );
        }
    }
    
    private function execute( $className, Update $update, ServiceLocator $sl, Publisher $pubsub )
    {
        $port = $sl->create( "com.servandserv.bot.domain.model.BotPort", [ $sl->get( "bot" ) ] );
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