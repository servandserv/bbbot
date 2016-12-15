<?php

namespace com\servandserv\Bot\Application\Events;

use \com\servandserv\Bot\Domain\Model\EventRepository;
use \com\servandserv\Bot\Domain\Model\Events\Subscriber;
use \com\servandserv\Bot\Domain\Model\Events\Event;
use \com\servandserv\Bot\Domain\Model\Events\EventStore;

class PersistEvent implements Subscriber
{

    private $rep;

    public function __construct( EventStore $rep )
    {
        $this->rep = $rep;
    }

    public function isSubscribedTo( Event $event )
    {
        return is_a( $event, 'com\servandserv\Bot\Domain\Model\Events\StoredEvent' );
    }
    public function handle( Event $event )
    {
        try{
            $this->rep->beginTransaction();
            $this->rep->append( $event );
            $this->rep->commit();
        } catch( \Exception $e ) {
            $this->rep->rollback();
            error_log( $e->getMessage().' file '.__FILE__.' line '.__LINE__ );
            throw new \Exception( "Storage error", 500 );
        }
    }
}