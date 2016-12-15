<?php

namespace com\servandserv\Bot\Infrastructure\Domain\Model\Viber;

class BotAdapter implements \com\servandserv\Bot\Domain\Model\BotPort
{
    const CONTEXT = "com.viber";

    protected $cli;
    protected $NS;
    protected $token;
    protected static $updates;

    public function __construct( \GuzzleHttp\Client $cli, $token, $NS )
    {
        $this->token = $token;
        $this->NS = $NS;
        $this->cli = $cli;
    }
    
    public function makeRequest( $name, array $args, callable $cb = NULL )
    {
        $clName = $this->NS."\\".$name;
        if( !class_exists( $clName ) ) throw new \Exception( "Class for VIEW name \"$name\" not exists." );
        $cl = new \ReflectionClass( $clName );
        $view = call_user_func_array( array( &$cl, 'newInstance' ), $args );
        $view->setToken( $this->token );
        
        $requests = $view->getRequests();
        foreach( $requests as $request ) {
            $watermark = round( microtime( true ) * 1000 );
            $resp = $this->cli->request( $request["method"], $request["command"], [ "json"=>$request["json"] ] );
            if( $json = json_decode( $resp->getBody(), TRUE ) ) {
                if( isset( $json["message_token"] ) && array_key_exists( "status", $json ) && $json["status"] == 0 ) {
                    $ret = ( new \com\servandserv\data\bot\Request() )
                        ->setId( $json["message_token"] )
                        ->setJson( $request["json"] )
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
            self::$updates = ( new \com\servandserv\data\bot\Updates() )->setContext( self::CONTEXT );
            if( !$json = json_decode( $in, TRUE ) ) throw new \Exception( "Error on decode update json in ".__FILE__." on line ".__LINE__ );
            if( !isset( $json["event"] ) ) throw new \Exception( "Error on json format - no event property in ".__FILE__." on line ".__LINE__ );
            $up = ( new \com\servandserv\data\bot\Update() )->setContext( self::CONTEXT );
            $chat = new \com\servandserv\data\bot\Chat();
            if( $json["event"] == "subscribed" ) {
                $up->setEvent( "Subscribed" );
                $chat->setId( $json["user"]["id"] );
                $user = $this->userFromJSON( $json["user"] );
                $chat->setUser( $user );
                return self::$updates;
            } else if( $json["event"] == "unsubscribed" ) {
                $up->setEvent( "Unsubscribed" );
                $chat->setId( $json["user_id"] );
                return self::$updates;
            } else if( $json["event"] == "conversation_started" ) {
                $up->setEvent( "GetStarted" );
                $chat->setId( $json["user"]["id"] );
                $user = $this->userFromJSON( $json["user"] );
                $chat->setUser( $user );
                return self::$updates;
            } else if( $json["event"] == "delivered" ) {
                $up->setEvent( "MessageDelivered" );
                $chat->setId( $json["user_id"] );
                $del = new \com\servandserv\data\bot\Delivery();
                $del->setMid( $json["message_token"] );
                $del->setWatermark( $json["timestamp"] );
                $up->setDelivery( $del );
            } else if( $json["event"] === "seen" ) {
                $up->setEvent( "MessageRead" );
                $chat->setId( $json["user_id"] );
                $read = new \com\servandserv\data\bot\Read();
                $read->setMid( $json["message_token"] );
                $read->setWatermark( $json["timestamp"] );
                $up->setRead( $read );
            } else if( $json["event"] == "failed" ) {
                $chat->setId( $json["user_id"] );
                throw new \Exception( print_r( $json, true ) );
                return self::$updates;
            } else if( $json["event"] == "message" ) {
                $up->setEvent( "MessageReceived" );
                $chat->setId( $json["sender"]["id"] );
                $user = $this->userFromJSON( $json["sender"] );
                $msg = new \com\servandserv\data\bot\Message();
                $msg->setUser( $user );
                $chat->setUser( $user );
                if( isset( $json["message"]["text"] ) ) {
                    $msg->setText( $json["message"]["text"] );
                }
                if( isset( $json["message"]["location"] ) ) {
                    $loc = new \com\servandserv\data\bot\Location();
                    $loc->setLatitude( $json["message"]["location"]["lat"] );
                    $loc->setLongitude( $json["message"]["location"]["lon"] );
                    $chat->setLocation( $loc );
                    $msg->setLocation( $loc );
                }
                if( isset( $json["message"]["contact"] ) ) {
                    $cont = $this->contactFromJSON( $json["message"]["contact"]["phone_number"] );
                    $chat->setContact( $cont );
                    $msg->setContact( $cont );
                }
                /**
                if( isset( $json["message"]["actionBody"] ) ) {
                    $com = new \com\servandserv\data\bot\Command();
                    $com->setName( $json["message"]["actionBody"] );
                    $up->setCommand( $com );
                }
                */
                $up->setMessage( $msg );
            }
            $up->setChat( $chat->setContext( self::CONTEXT ) );
            self::$updates->setUpdate( $up );
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
    
    private function userFromJSON( $json )
    {
        $user = new \com\servandserv\data\bot\User();
        $user->setId( $json["id"] );
        $user->setFirstName( $json["name"] );
        if( isset( $json["language"] ) ) $user->setLocale( $json["language"] );
        
        return $user;
    }
    
    private function contactFromJSON( $json )
    {
        $c = new \com\servandserv\data\bot\Contact();
        $c->setPhoneNumber( str_replace( ["+","(",")","[","]","-"], "", $json["phone_number"] ) );
        return $c;
    }
}