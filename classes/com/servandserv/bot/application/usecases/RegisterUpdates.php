<?php

namespace com\servandserv\bot\application\usecases;

use \com\servandserv\bot\domain\model\UpdateRepository;
use \com\servandserv\bot\domain\model\BotPort;
use \com\servandserv\bot\domain\model\events\Publisher;
use \com\servandserv\bot\domain\model\events\UpdatesRegisteredEvent;
use \com\servandserv\bot\domain\model\events\ErrorOccuredEvent;
use \com\servandserv\bot\domain\model\events\ExceptionOccuredEvent;
use \com\servandserv\data\bot\Update;
use \com\servandserv\happymeal\ErrorsHandler;
use \com\servandserv\happymeal\errors\Error;
use \com\servandserv\happymeal\errors\Errors;

class RegisterUpdates {

    protected $ur;
    protected $pubsub;

    public function __construct(UpdateRepository $ur, Publisher $pubsub) {
        $this->ur = $ur;
        $this->pubsub = $pubsub;
    }

    public function execute(BotPort $port) {
        // отлавливаем все ошибки, мессенджеры всегда правы
        // ошибки посылаем через событие ErrorOccuredEvent
        try {
            // validate request
            $updates = $port->getUpdates()->getUpdate();
            foreach ($updates as $update) {
                $eh = new ErrorsHandler();
                if ($update->validateType($eh)) {
                    // промолчим
                    //$eh->handleError( ( new Error() )->setDescription( "Validation error on update: ".$update->toJSON() ) );
                    //$this->pubsub->publish( new ErrorOccuredEvent( $eh->getErrors() ) );
                } else {
                    // сохраним каждый апдейт отдельно
                    try {
                        $this->ur->beginTransaction();
                        $this->ur->register($update);
                        $this->ur->commit();
                    } catch (\Exception $e) {
                        $this->ur->rollback();
                        $this->pubsub->publish(new ExceptionOccuredEvent($e));
                        // silence
                    }
                }
            }
            // публикуем событие регистрации апдейтов. Делаем это один раз сразу по всем апдейтам
            $this->pubsub->publish(new UpdatesRegisteredEvent($port->getUpdates(), $port->getContext()));
        } catch (\Exception $e) {
            $this->pubsub->publish(new ExceptionOccuredEvent($e));
            //silence
        }
        $port->response();
    }

}
