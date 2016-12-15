<?php

namespace com\servandserv\Bot\Application\Commands;

abstract class AbstractCommand
{

    public static $pattern = NULL;
    public static $command = NULL;
    public static $name = NULL;

    public static function getName()
    {
        return self::$name;
    }

    public static function fit( \com\servandserv\data\bot\Update $up )
    {
        $text = NULL;
        if( $up->getMessage() && $up->getMessage()->getText() ) $text = $up->getMessage()->getText();
        if( $up->getCommand() ) $text = $up->getCommand()->getName();
        if( $text && ( 
            ( static::$command && preg_match( "/^\/?(".static::$command.")(\s.+)?/i", $text, $m ) )  ||
            ( static::$pattern && preg_match( static::$pattern, $text, $m ) ) ||
            ( static::$name && $text == static::$name )
        ) ) {
            $com = new \com\servandserv\data\bot\Command();
            if( isset( static::$command ) ) $com->setName( static::$command );
            if( isset( $m[1] ) ) $com->setAlias( $m[1] );
            if( isset( $m[2] ) ) $com->setArguments( $m[2] );
            $up->setCommand( $com );
            
            return TRUE;
        }
        
        return FALSE;
    }

    abstract public function execute( 
        \com\servandserv\data\bot\Update $up,
        \com\servandserv\Bot\Application\ServiceLocator $sl,
        \com\servandserv\data\bot\Commands $coms,
        \com\servandserv\Bot\Domain\Model\BotPort $port
    );
}