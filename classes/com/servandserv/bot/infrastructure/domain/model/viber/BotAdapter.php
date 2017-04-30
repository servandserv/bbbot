<?php

namespace com\servandserv\bot\infrastructure\domain\model\viber;

use \com\servandserv\bot\domain\model\CurlClient;
use \com\servandserv\bot\domain\model\CurlException;
use \com\servandserv\bot\domain\model\BotPort;
use \com\servandserv\bot\domain\model\UserNotFoundException;

use \com\servandserv\happymeal\xml\schema\AnyType;

use \com\servandserv\data\bot\Request;
use \com\servandserv\data\bot\Update;
use \com\servandserv\data\bot\Updates;
use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\Delivery;
use \com\servandserv\data\bot\Read;
use \com\servandserv\data\bot\Contact;
use \com\servandserv\data\bot\Location;
use \com\servandserv\data\bot\Message;
use \com\servandserv\data\bot\User;

use \com\viber\data\bot\Update as ViberUpdate;
use \com\viber\data\bot\SenderType;
use \com\viber\data\bot\UserType;

class BotAdapter implements BotPort
{
    const CONTEXT = "com.viber";

    protected $cli;
    protected $NS;
    protected $token;
    protected static $updates;

    public function __construct( CurlClient $cli, $token, $NS )
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
        //try {
            foreach( $requests as $request ) {
                $watermark = round( microtime( true ) * 1000 );
                $resp = $this->cli->request( $request );
                if( $json = json_decode( $resp->getBody(), TRUE ) ) {
                    if( isset( $json["message_token"] ) && array_key_exists( "status", $json ) && $json["status"] == 0 ) {
                        $ret = ( new Request() )
                            ->setId( $json["message_token"] )
                            ->setJson( $request->getContent() )
                            ->setWatermark( $watermark );
                        if( $cb ) $cb( $ret );
                    } else if ( array_key_exists( "status", $json ) && in_array( intval( $json["status"] ) , [ 5, 6, 7 ] ) ) {
                        // клиент отвалился или его никогда небыло скажем об этом миру
                        // https://developers.viber.com/api/rest-bot-api/index.html#errorCodes
                        throw new UserNotFoundException( $json["status_message"] );
                    } else {
                        // неизвестная ошибка, не плохо бы посмотреть на нее
                        throw new \Exception( $json["status_message"]." on request: ".$request->toXmlStr() );
                    }
                }
            }
        //} catch (\Exception $e ) {
            //trigger_error($e->getMessage());
        //}
    }
    
    public function getUpdates()
    {
        if( NULL == self::$updates ) {
            $in = file_get_contents( "php://input" );
            self::$updates = ( new Updates() )->setContext( self::CONTEXT );
            if( !$json = json_decode( $in, TRUE ) ) throw new \Exception( "Error on decode update json in ".__FILE__." on line ".__LINE__ );
            // убрал проверку подписи надо с ней разбираться
            //if( !$this->checkSignature( json_encode( $json ) ) ) return self::$updates;
            $vup = ( new ViberUpdate() )->fromJSONArray( $json );
            $up = ( new Update() )->setContext( self::CONTEXT )->setId( intval(microtime(true)*1000) )->setRaw( $in );
            $chat = new Chat();
            switch( $vup->getEvent() ) {
                case "webhook":
                    return self::$updates;
                    break;
                case "subscribed":
                    $up->setEvent( "RECEIVED" );
                    $chat->setId( $vup->getUser()->getId() );
                    $sender =  $this->userFromUserType( $vup->getUser() );
                    $chat->setUser( $sender );
                    $message = ( new Message() )->setUser( $sender )->setDt( intval( microtime( true )*1000 ) )->setId( $up->getId() );
                    $message->setText( "help" ); 
                    break;
                case "unsubscribed":
                    $up->setEvent( "RECEIVED" );
                    $chat->setId( $vup->getUser_id() );
                    return self::$updates;
                    break;
                case "conversation_started":
                    $up->setEvent( "RECEIVED" );
                    $chat->setId( $vup->getUser()->getId() );
                    $sender =  $this->userFromUserType( $vup->getUser() );
                    $chat->setUser( $sender );
                    $message = ( new Message() )->setUser( $sender )->setDt( intval( microtime( true )*1000 ) )->setId( $up->getId() );
                    $message->setText( "help" ); 
                    break;
                case "delivered":
                    $up->setEvent( "DELIVERED" );
                    $chat->setId( $vup->getUser_id() );
                    $del = ( new Delivery() )->setMid( $vup->getMessage_token() )->setWatermark( $vup->getTimestamp() );
                    $up->setDelivery( $del );
                    break;
                case "seen":
                    $up->setEvent( "READ" );
                    $chat->setId( $vup->getUser_id() );
                    $read = ( new Read() )->setMid( $vup->getMessage_token() )->setWatermark( $vup->getTimestamp() );
                    $up->setRead( $read );
                    break;
                case "failed":
                    throw new \Exception( $vup->toXmlStr() );
                    break;
                case "message":
                    $up->setEvent( "RECEIVED" );
                    $chat->setId( $vup->getSender()->getId() );
                    $sender = $this->userFromSenderType( $vup->getSender() );
                    $chat->setUser( $sender );
                    $message = ( new Message() )->setUser( $sender )->setDt( intval( microtime( true )*1000 ) )->setId( $up->getId() );
                    $message->setText( $vup->getMessage()->getText() );
                    if( $l = $vup->getMessage()->getLocation() ) {
                        $loc = ( new Location() )->setLatitude( $l->getLat() )->setLongitude( $l->getLon() );
                        $chat->setLocation( $loc );
                        $message->setLocation( $loc );
                    }
                    if( $c = $vup->getMessage()->getContact() ) {
                        $contact = ( new Contact() )
                            ->setPhoneNumber( str_replace( ["+","(",")","[","]","-"], "", $c->getPhone_number() ) );
                        $contactuser = ( new User() )->setFirstName( $c->getUsername() );
                        try {
                            if( $this->checkId( $sender->getAvatar(), $c->getAvatar() ) ) {
                                $contactuser->setId( $sender->getId() )->setAvatar( $c->getAvatar() );
                            }
                        } catch( CurlException $e ) {
                            // не знаю пока что мне с этим делать
                            // приходят ошибки vibera о недоступности аватара клиента
                        }
                        $contact->setUser( $contactuser );
                        $chat->setContact( $contact );
                        $message->setContact( $contact );
                    }
                    $up->setMessage( $message );
                    break;
            }
            $up->setChat( $chat->setContext( self::CONTEXT ) );
            self::$updates->setUpdate( $up );
        }
        return self::$updates;
    }
    
    private function userFromUserType( UserType $ut = null)
    {
        if( !$ut ) return null;
        $u = ( new User() )
            ->setId( $ut->getId() )
            ->setFirstName( $ut->getName() )
            ->setAvatar( $ut->getAvatar() )
            ->setLocale( $ut->getLanguage());
            
        return $u;
    }
    
    private function userFromSenderType( SenderType $st = null )
    {
        if( !$st ) return null;
        $u = ( new User() )
            ->setId( $st->getId() )
            ->setFirstName( $st->getName() )
            ->setAvatar( $st->getAvatar() )
            ->setLocale( $st->getLanguage());
            
        return $u;
    }
    
    
    public function response( AnyType $anyType = NULL, $code = 200 )
    {
        if( !headers_sent() ) {
            $protocol = ( isset( $_SERVER["SERVER_PROTOCOL"] ) ? $_SERVER["SERVER_PROTOCOL"] : "HTTP/1.0" );
            header( $protocol . " " . $code . " " . http_response_code( $code ) );
        }
        exit;
    }
    
    private function checkSignature( $in )
    {
        $hash = hash_hmac( "sha256", $in, $this->token );
        $headers = [];
        foreach( $_SERVER as $k=>$v ) {
            if ( substr( $k, 0, 5) == "HTTP_" ) {
                $name = str_replace( " ", "-", ucwords( strtolower( str_replace( "_", " ", substr( $k, 5 ) ) ) ) );
                $headers[$name] = $v;
                if( $name == "X-Viber-Content-Signature" ) {
                    return $hash == $v;
                }
            }
        } 
    }
    
    /**
     *  Проверяем совпадение аватаров пользователя отправившего контакт и самого контакта
     * если они совпадают, то значит отправитель передал свой контактный номер. если нет, то контактный номер чужой.
     */
    private function checkId( $senderAvatar, $contactAvatar )
    {
            $senderAvatar = filter_var( $senderAvatar , FILTER_VALIDATE_URL );
            $contactAvatar = filter_var( $contactAvatar, FILTER_VALIDATE_URL );
            if( $senderAvatar && $contactAvatar ) {
                $senderId = $this->cli->getEffectiveUrl( $senderAvatar );
                $contactId = $this->cli->getEffectiveUrl( $contactAvatar );
            
                return $senderId == $contactId;
            }
    }
    
}