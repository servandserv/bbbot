<?php

namespace com\servandserv\Bot\Infrastructure\Domain\Model\FB;

use \com\servandserv\Bot\Domain\Model\BotPort;
use \com\servandserv\Bot\Domain\Model\CurlClient;
use \com\servandserv\data\bot\Updates;
use \com\servandserv\data\bot\Update;
use \com\servandserv\data\bot\Request;
use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\Message;
use \com\servandserv\data\bot\Delivery;
use \com\servandserv\data\bot\Read;
use \com\servandserv\data\bot\Command;
use \com\servandserv\data\bot\Contact;
use \com\servandserv\data\bot\Location;

class BotAdapter implements BotPort
{
    const CONTEXT = "com.facebook";

    protected $cli;
    protected $NS;
    protected static $updates;

    public function __construct( CurlClient $cli, $NS )
    {
        $this->cli = $cli;
        $this->NS = $NS;
    }
    
    public function makeRequest( $name, array $args, callable $cb = NULL )
    {
        $clName = $this->NS."\\".$name;
        if( !class_exists( $clName ) ) throw new \Exception( "Class for VIEW name \"$name\" not exists." );
        $cl = new \ReflectionClass( $clName );
        $view = call_user_func_array( array( &$cl, 'newInstance' ), $args );
        
        $requests = $view->getRequests();
        foreach( $requests as $request ) {
            $watermark = round( microtime( true ) * 1000 );
            $resp = $this->cli->request( $request );
            if( $json = json_decode( $resp->getBody(), TRUE ) ) {
                if( isset( $json["message_id"] ) ) {
                    $ret = ( new Request() )
                        ->setId( $json["message_id"] )
                        ->setJson( $request->getContent() )
                        ->setWatermark( $watermark );
                    if( $cb ) $cb( $ret );
                }
            }
        }
    }
    
    public function getUpdates()
    {
        if( NULL == self::$updates ) {
            $in = file_get_contents( "php://input" );
            self::$updates = ( new Updates() )->setContext( self::CONTEXT );
            if( !$json = json_decode( $in, TRUE ) ) throw new \Exception( "Error on decode update json in ".__FILE__." on line ".__LINE__ );
            if( !isset( $json["entry"] ) || !is_array( $json["entry"] ) ) throw new \Exception( "Error no entry node in update json in ".__FILE__." on line ".__LINE__ );
            foreach( $json["entry"] as $entry ) {
                if( !isset( $entry["messaging"] ) || !is_array( $entry["messaging"] ) ) continue;
                foreach( $entry["messaging"]  as $messaging ) {
                    $up = new Update();
                    $up->setContext( self::CONTEXT );
                    $chat = new Chat();
                    $chat->setId( $messaging["sender"]["id"] );
                    $chat->setContext( self::CONTEXT );
                    $chat->setType( "private" );
                    // todo
                    //if( isset( $messaging["delivery"] ) || isset( $messaging["read"] ) ) continue;
                    if( isset( $messaging["delivery"] ) ) {
                        $up->setEvent( "MessageDelivered" );
                        $del = new Delivery();
                        if( isset( $messaging["delivery"]["mids"] ) && is_array( $messaging["delivery"]["mids"] ) ) {
                            foreach( $messaging["delivery"]["mids"] as $mid ) {
                                $del->setMid( $mid );
                            }
                        }
                        $del->setWatermark( $messaging["delivery"]["watermark"] );
                        $del->setSeq( $messaging["delivery"]["seq"] );
                        $up->setDelivery( $del );
                    }
                    if( isset( $messaging["read"] ) ) {
                        $up->setEvent( "MessageRead" );
                        $read = new Read();
                        /**
                        if( isset( $messaging["read"]["mids"] ) && is_array( $messaging["read"]["mids"] ) ) {
                            foreach( $messaging["read"]["mids"] as $mid ) {
                                $del->setMid( $mid );
                            }
                        }
                        */
                        $read->setWatermark( $messaging["read"]["watermark"] );
                        $read->setSeq( $messaging["read"]["seq"] );
                        $up->setRead( $read );
                    }
                    if( isset( $messaging["message"] ) ) {
                        $up->setEvent( "MessageReceived" );
                        $m = $messaging["message"];
                        // нашли сообщение
                        $message = new Message();
                        if( isset( $m["text"] ) )
                        $message->setText( $m["text"] );
                        if( isset( $m["attachments"] ) && is_array( $m["attachments"]) ) {
                            foreach( $m["attachments"] as $at ) {
                                if( isset( $at["type"] ) && $at["type"] == "location" ) {
                                    $loc = new Location();
                                    $loc->setLatitude( $at["payload"]["coordinates"]["lat"] );
                                    $loc->setLongitude( $at["payload"]["coordinates"]["long"] );
                                    $message->setLocation( $loc );
                                    $chat->setLocation( $loc );
                                }
                            }
                        }
                        $up->setMessage( $message );
                    }
                    $up->setChat( $chat );
                    if( isset( $messaging["postback"] ) ) {
                        $up->setEvent( "CallbackReceived" );
                        $command = new Command();
                        $command->setName( substr($messaging["postback"]["payload"], 1 ) )->setArguments( "" );
                        $up->setCommand( $command );
                    }
                    self::$updates->setUpdate( $up );
                }
            }
        }
        return self::$updates;
    }
    
    public function response( \com\servandserv\happymeal\XML\Schema\AnyType $anyType = NULL, $code = 200 )
    {
        if( !headers_sent() ) {
            $protocol = ( isset( $_SERVER["SERVER_PROTOCOL"] ) ? $_SERVER["SERVER_PROTOCOL"] : "HTTP/1.0" );
            header( $protocol . " " . $code . " " . http_response_code( $code ) );
        }
        exit;
    }
}