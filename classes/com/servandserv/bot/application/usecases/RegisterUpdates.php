<?php

namespace com\servandserv\bot\application\usecases;

use \com\servandserv\bot\domain\model\UpdateRepository;
use \com\servandserv\bot\domain\model\BotPort;
use \com\servandserv\bot\domain\model\events\Publisher;
use \com\servandserv\bot\domain\model\events\UpdatesRegisteredEvent;
use \com\servandserv\bot\domain\model\events\ErrorOccuredEvent;
use \com\servandserv\data\bot\Update;
use \com\servandserv\happymeal\ErrorsHandler;
use \com\servandserv\happymeal\errors\Error;


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
        // отлавливаем все ошибки и просто тупо молчим в ответ, мессенджеры всегда правы
        try {
            // validate request
            $updates = $port->getUpdates()->getUpdate();
            foreach( $updates as $update ) {
                $eh = new ErrorsHandler();
                if( $update->validateType( $eh ) ) {
                    $this->pubsub->publish( new ErrorOccuredEvent( $eh->getErrors() ) );
                } else {
                    try {
                        $this->ur->beginTransaction();
                        $this->ur->register( $update );
                        $this->ur->commit();
                    } catch( \Exception $e ) {
                        $this->ur->rollback();
                        $this->pubsub->publish( new ErrorOccuredEvent(
                            ( new Error() )->setDescription( $e->getMessage() . " in ". $e->getFile() . " on " . $e->getLine() )
                        ));
                        // silence
                    }
                }
            }
            //делаем это один раз
            $this->pubsub->publish( new UpdatesRegisteredEvent( $port->getUpdates() ) );
        } catch( \Exception $e ) {
            $this->pubsub->publish( new ErrorOccuredEvent(
                ( new Error() )->setDescription( $e->getMessage() . " in ". $e->getFile() . " on " . $e->getLine() )
            ));
            //silence
        }
        $port->response();
    }
}