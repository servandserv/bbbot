<?php

namespace com\servandserv\Bot\Domain\Model\Events;

use \com\servandserv\data\bot\Update;

class UpdateRegisteredEvent extends InMemoryEvent
{
    public function __construct( Update $update )
    {
        $this->update = $update;
        $this->occuredOn = intval( microtime( true ) * 1000 );
    }
    
    public function occuredOn()
    {
        return $this->occuredOn;
    }
    
    public function getUpdate()
    {
        return $this->update;
    }
}