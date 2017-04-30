<?php

namespace com\servandserv\bot\application\events;

use \com\servandserv\bot\domain\model\EventRepository;
use \com\servandserv\bot\domain\model\events\Subscriber;
use \com\servandserv\bot\domain\model\events\Event;
use \com\servandserv\bot\domain\model\events\EventStore;
use \com\servandserv\bot\domain\model\events\ExceptionOccuredEvent;
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
        return ( $event instanceof \com\servandserv\bot\domain\model\events\StoredEvent );
    }
    public function handle( Event $event )
    {
        try{
            $this->rep->beginTransaction();
            $this->rep->append( $event );
            $this->rep->commit();
        } catch( \Exception $e ) {
            $this->rep->rollback();
            $pubsub->publish( new \ExceptionOccuredEvent( $e ) );
            throw new \Exception( "Storage error", 500, $e );
        }
    }
}