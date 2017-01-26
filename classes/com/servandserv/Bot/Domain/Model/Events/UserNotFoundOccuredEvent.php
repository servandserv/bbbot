<?php

namespace com\servandserv\Bot\Domain\Model\Events;

use \com\servandserv\data\bot\Chat;
use \com\servandserv\happymeal\XMLAdaptor;
use \com\servandserv\happymeal\XML\Schema\AnyType;

class UserNotFoundOccuredEvent extends InMemoryEvent
{

    private $code;
    private $message;
    private $chat;

    public function __construct( $code, $message, Chat $chat )
    {
        $this->code = $code;
        $this->message = $message;
        $this->chat = $chat;
        $this->occuredOn = intval( microtime( true ) * 1000 );
        $this->type = "UserNotFoundOccuredEvent";
    }
    
    public function getCode()
    {
        return $this->code;
    }
    
    public function getMessage()
    {
        return $this->message;
    }
    
    public function getChat()
    {
        return $this->chat;
    }
    
    public function occuredOn()
    {
        return $this->occuredOn;
    }
}