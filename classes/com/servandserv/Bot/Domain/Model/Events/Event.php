<?php

namespace com\servandserv\Bot\Domain\Model\Events;

interface Event
{
    public function setPubSub( Publisher $pubsub );
    public function getPubSub();
    public function occuredOn();
    public function toReadableStr();
}