<?php

namespace com\servandserv\bot\domain\model\events;

class Publisher
{
    private $subscribers;
    private static $instance = NULL;
    
    private function __construct()
    {
        $this->subscribers = [];
    }
    
    public static function getInstance()
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
        $event->setPubSub( $this );//добавим в событие ссылку на публишер, чтобы использовать цепочки событий
        foreach( $this->subscribers as $sub ) {
            if( $sub->isSubscribedTo( $event ) ) {
                $sub->handle( $event );
            }
        }
    }
}