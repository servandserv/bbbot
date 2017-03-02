<?php

namespace com\servandserv\bot\domain\model\events;

interface Subscriber
{
    public function isSubscribedTo( Event $event );
    public function handle( Event $event );
}