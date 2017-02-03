<?php

namespace com\servandserv\Bot\Domain\Model\Events;

abstract class InMemoryEvent implements Event
{
    protected $pubsub;
    
    public function setPubSub( Publisher $pubsub ) 
    {
        $this->pubsub = $pubsub;
        return $this;
    }
    public function getPubSub()
    {
        return $this->pubsub;
    }
    abstract public function occuredOn();
    abstract public function toReadableStr();
}