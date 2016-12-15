<?php

namespace com\servandserv\Bot\Domain\Model\Events;

interface EventStore
{
    public function append( StoredEvent $event );
    public function allEventsSince( $watermark );
    public function allEventsAfter( $id );
}