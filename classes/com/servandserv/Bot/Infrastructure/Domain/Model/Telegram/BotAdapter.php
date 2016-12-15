<?php

namespace com\servandserv\Bot\Infrastructure\Domain\Model\Telegram;

class BotAdapter implements \com\servandserv\Bot\Domain\Model\BotPort
{
    const CONTEXT = "org.telegram";

    protected $cli;
    protected $NS;
    protected static $updates;

    public function __construct( \GuzzleHttp\Client $cli, $NS )
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
            $resp = $this->cli->request( $request["method"], $request["command"], [ "json"=>$request["json"] ] );
            if( $json = json_decode( $resp->getBody(), TRUE ) ) {
                if( isset( $json["result"] ) && isset( $json["result"]["message_id"] ) ) {
                    $ret = ( new \com\servandserv\data\bot\Request() )
                        ->setId( $json["result"]["message_id"] )
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
            $up = ( new \com\servandserv\data\bot\Update() )->setContext( self::CONTEXT );
            $chat = new \com\servandserv\data\bot\Chat();
            $up->setId( $json["update_id"] );
            if( isset( $json["callback_query"] ) ) {
                $up->setEvent("CallbackReceived");
                if( isset( $json["callback_query"]["data"] ) ) {
                    $com = new \com\servandserv\data\bot\Command();
                    $com->setName( $json["callback_query"]["data"] );
                    $up->setCommand( $com );
                }
                if( isset( $json["callback_query"]["from"] ) ) {
                    $chat->setUser( $this->userFromJSON( $json["callback_query"]["from"] ) );
                }
                if( isset( $json["callback_query"]["chat_instance"] ) ) {
                    $chat->setId( $json["callback_query"]["chat_instance"] );
                }
            }
            if(isset( $json["message"] ) ) {
                $up->setEvent( "MessageReceived" );
                $m = new \com\servandserv\data\bot\Message();
                $m->setId( $json["message"]["message_id"] );
                $m->setDt( $json["message"]["date"] );
                $chat = $this->chatFromJSON( $json["message"]["chat"] );
                if( isset( $json["message"]["text"] ) ) {
                    $m->setText( $json["message"]["text"] );
                }
                if( isset( $json["message"]["from"] ) ) {
                    $m->setUser( $this->userFromJSON( $json["message"]["from"] ) );
                }
                if( isset( $json["message"]["location"] ) ) {
                    $loc = $this->locationFromJSON( $json["message"]["location"] );
                    $m->setLocation( $loc );
                    $chat->setLocation( $loc );
                }
                if( isset( $json["message"]["contact"] ) ) {
                    $c = $this->contactFromJSON( $json["message"]["contact"] );
                    $m->setContact( $c );
                    $chat->setContact( $c );
                }
                if( isset( $json["message"]["entities"] ) ) {
                    foreach( $json["message"]["entities"] as $ent ) {
                        if( $ent["type"] == "bot_command" && isset( $json["message"]["text"] ) ) {
                            $name = substr( $json["message"]["text"], intval( $ent["offset"] ) + 1, intval( $ent["offset"] ) + intval( $ent["length"] ) );
                            $arg = substr( $json["message"]["text"], intval( $ent["offset"] ) + 1 + intval( $ent["length"] ) );
                            $com = new \com\servandserv\data\bot\Command();
                            $com->setName( $name );
                            $com->setArguments( $arg );
                            $up->setCommand( $com );
                        }
                    }
                }
                $up->setMessage( $m );
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
        $user->setFirstName( $json["first_name"] );
        $user->setLastName( isset( $json["last_name"] ) ? $json["last_name"] : "" );
        $user->setNickname( isset( $json["username"] ) ? $json["username"] : "" );
        return $user;
    }
    
    private function chatFromJSON( $json )
    {
        $chat = new \com\servandserv\data\bot\Chat();
        $chat->setId( $json["id"] );
        $chat->setType( $json["type"] );
        if( isset( $json["last_name"] ) || isset( $json["first_name"] ) || isset( $json["username"] ) ) {
            $user = new \com\servandserv\data\bot\User();
            $user->setFirstName( isset( $json["first_name"] ) ? $json["first_name"] : "" );;
            $user->setLastName( isset( $json["last_name"] ) ? $json["last_name"] : "" );
            $user->setNickname( isset( $json["username"] ) ? $json["username"] : "" );
            $chat->setUser( $user );
        }
        return $chat;
    }
    
    private function locationFromJSON( $json )
    {
        $loc = new \com\servandserv\data\bot\Location();
        $loc->fromMarkupArray( $json );
        return $loc;
    }
    
    private function contactFromJSON( $json )
    {
        $c = new \com\servandserv\data\bot\Contact();
        $c->setPhoneNumber( str_replace( ["+","(",")","[","]","-"], "", $json["phone_number"] ) );
        return $c;
    }
}