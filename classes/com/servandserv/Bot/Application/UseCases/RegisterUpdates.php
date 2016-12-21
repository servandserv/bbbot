<?php

namespace com\servandserv\Bot\Application\UseCases;

use \com\servandserv\Bot\Domain\Model\UpdateRepository;
use \com\servandserv\Bot\Domain\Model\BotPort;
use \com\servandserv\Bot\Domain\Model\Events\UpdateRegisteredEvent;
use \com\servandserv\data\bot\Update;
use \com\servandserv\happymeal\ErrorsHandler;
use \com\servandserv\Bot\Domain\Model\Events\Publisher;

class RegisterUpdates
{

    protected $ur;
    protected $pubsub;

    public function __construct( UpdateRepository $ur, Publisher $pubsub  )
    {
        $this->ur = $ur;
        $this->pubsub = $pubsub;
    }

    public function execute( BotPort $port )
    {
        // todo validate client
        //if( $_SESSION[session_name()] == "Unknown" ) $this->port->throwException( "Forbidden", 403 );
        
        // отлавливаем все ошибки и просто тупо молчим в ответ, мессенджеры всегда правы
        try {
            // validate request
            $updates = $port->getUpdates()->getUpdate();
            foreach( $updates as $update ) {
                $eh = new ErrorsHandler();
                if( $update->validateType( $eh ) ) {
                    error_log( $eh->getErrors()->toXmlStr() );
                } else {
                    try {
                        $this->ur->beginTransaction();
                        $this->ur->register( $update );
                        $this->ur->commit();
                        $this->pubsub->publish( new UpdateRegisteredEvent( $update ) );
                    } catch( \Exception $e ) {
                        $this->ur->rollback();
                        error_log( $e->getMessage() . " in ". $e->getFile() . " on " . $e->getLine() );
                        // silence
                    }
                }
            }
            $port->response();
        } catch( \Exception $e ) {
            error_log( $e->getMessage() . " in " . $e->getFile() . " on ". $e->getLine() );
            //silence
        }
    }
}