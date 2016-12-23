<?php

namespace com\servandserv\Bot\Application\Events;

use \com\servandserv\Bot\Domain\Model\EventRepository;
use \com\servandserv\Bot\Domain\Model\Events\Subscriber;
use \com\servandserv\Bot\Domain\Model\Events\Event;
use \com\servandserv\Bot\Domain\Model\Events\EventStore;
use \com\servandserv\Bot\Domain\Model\Events\ErrorOccuredEvent;
use \com\servandserv\happymeal\errors\Error;

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
            $event->getPubSub()->publish( new ErrorOccuredEvent(
                ( new Error() )->setDescription( $e->getMessage()." in ".$e->getFile()." on ".$e->getLine() )
            ));
            throw new \Exception( "Storage error", 500 );
        }
    }
}