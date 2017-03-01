<?php

namespace com\servandserv\Bot\Domain\Model\Events;

use \com\servandserv\data\bot\Chat;
use \com\servandserv\happymeal\XMLAdaptor;
use \com\servandserv\happymeal\XML\Schema\AnyType;

class UserNotFoundOccuredEvent extends InMemoryEvent
{

    private $chat;

    public function __construct( Chat $chat )
    {
        $this->chat = $chat;
        $this->occuredOn = intval( microtime( true ) * 1000 );
        $this->type = "UserNotFoundOccuredEvent";
    }
    
    public function getChat()
    {
        return $this->chat;
    }
    
    public function occuredOn()
    {
        return $this->occuredOn;
    }
    
    public function toReadableStr()
    {
        return $this->chat->toJSON();
    }
}