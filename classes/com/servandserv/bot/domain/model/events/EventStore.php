<?php

namespace com\servandserv\bot\domain\model\events;

interface EventStore
{
    public function append( StoredEvent $event );
    public function allEventsSince( $watermark );
    public function allEventsAfter( $id );
}