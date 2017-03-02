<?php

namespace com\servandserv\bot\application\commands;

use \com\servandserv\bot\domain\model\events\Publisher;
use \com\servandserv\bot\application\ServiceLocator;
use \com\servandserv\data\bot\Commands;
use \com\servandserv\data\bot\Update;
use \com\servandserv\data\bot\Command;
use \com\servandserv\bot\domain\model\BotPort;

abstract class AbstractCommand
{

    public static $pattern = NULL;
    public static $command = NULL;
    public static $name = NULL;
    protected $pubsub;

    public function __construct( Publisher $pubsub )
    {
        $this->pubsub = $pubsub;
    }

    public static function getName()
    {
        return self::$name;
    }

    public static function fit( Update $up )
    {
        $text = NULL;
        if( $up->getMessage() && $up->getMessage()->getText() ) $text = $up->getMessage()->getText();
        if( $up->getCommand() ) $text = $up->getCommand()->getName();
        if( $text && ( 
            ( static::$command && preg_match( "/^\/?(".static::$command.")(\s.+)?/i", $text, $m ) )  ||
            ( static::$pattern && preg_match( static::$pattern, $text, $m ) ) ||
            ( static::$name && $text == static::$name )
        ) ) {
            $com = new Command();
            if( $up->getCommand() && $up->getCommand()->getId() ) $com->setId( $up->getCommand()->getId() );
            if( isset( static::$command ) ) {
                $com->setName( static::$command );
            } else if( isset( $m[1] ) ) {
                $com->setName( $m[1] );
            }
            if( isset( $m[1] ) ) $com->setAlias( $m[1] );
            if( isset( $m[2] ) ) $com->setArguments( trim( $m[2] ) );
            $up->setCommand( $com );
            
            return TRUE;
        }
        
        return FALSE;
    }

    abstract public function execute( 
        Update $up,
        ServiceLocator $sl,
        Commands $coms,
        BotPort $port
    );
}