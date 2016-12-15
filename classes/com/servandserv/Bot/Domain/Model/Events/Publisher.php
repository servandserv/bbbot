<?php

namespace com\servandserv\Bot\Domain\Model\Events;

class Publisher
{
    private $subscribers;
    private static $instance = NULL;
    
    private function __construct()
    {
        $this->subscribers = [];
    }
    
    public function getInstance()
    {
        if( static::$instance === NULL ) {
            static::$instance = new self();
        }
        return static::$instance;
    }
    
    public function subscribe( Subscriber $sub )
    {
        $this->subscribers[] = $sub;
    }
    
    public function publish( Event $event )
    {
        foreach( $this->subscribers as $sub ) {
            if( $sub->isSubscribedTo( $event ) ) {
                $sub->handle( $event );
            }
        }
    }
}