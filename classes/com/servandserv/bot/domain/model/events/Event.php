<?php

namespace com\servandserv\bot\domain\model\events;

interface Event
{
    public function setPubSub( Publisher $pubsub );
    public function getPubSub();
    public function occuredOn();
    public function toReadableStr();
}