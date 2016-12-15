<?php

namespace com\servandserv\Bot\Domain\Model\Events;

interface Subscriber
{
    public function isSubscribedTo( Event $event );
    public function handle( Event $event );
}