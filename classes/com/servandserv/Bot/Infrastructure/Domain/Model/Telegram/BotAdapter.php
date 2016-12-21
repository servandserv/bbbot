<?php

namespace com\servandserv\Bot\Infrastructure\Domain\Model\Telegram;

use \com\servandserv\Bot\Domain\Model\CurlClient;
use \org\telegram\data\bot\Update as TelegramUpdate;
use \com\servandserv\data\bot\Updates;
use \com\servandserv\data\bot\Update;
use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\User;
use \com\servandserv\data\bot\Message;
use \com\servandserv\data\bot\Location;
use \com\servandserv\data\bot\Contact;
use \com\servandserv\data\bot\Command;

class BotAdapter implements \com\servandserv\Bot\Domain\Model\BotPort
{
    const CONTEXT = "org.telegram";
    const LIMIT_PER_SECOND = 30;

    protected $cli;
    protected $NS;
    protected $messagesPerSecond;
    protected static $updates;

    public function __construct( CurlClient $cli, $NS )
    {
        $this->cli = $cli;
        $this->NS = $NS;
    }
    
    public function makeRequest( $name, array $args, callable $cb = NULL )
    {
        $timer = \com\servandserv\Bot\Domain\Service\Timer::getInstance( intval( self::LIMIT_PER_SECOND *0.75 ) );
        
        $clName = $this->NS."\\".$name;
        if( !class_exists( $clName ) ) throw new \Exception( "Class for VIEW name \"$name\" not exists." );
        $cl = new \ReflectionClass( $clName );
        $view = call_user_func_array( array( &$cl, 'newInstance' ), $args );
        
        $requests = $view->getRequests();
        foreach( $requests as $request ) {
            $timer->next();// следующая отправка
            $watermark = round( microtime( true ) * 1000 );
            $resp = $this->cli->request( $request );
            if( $json = json_decode( $resp->getBody(), TRUE ) ) {
                if( isset( $json["result"] ) && isset( $json["result"]["message_id"] ) ) {
                    $ret = ( new \com\servandserv\data\bot\Request() )
                        ->setId( $json["result"]["message_id"] )
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
            self::$updates = ( new Updates() )->setContext( self::CONTEXT );
            $in = file_get_contents( "php://input" );
            if( !$json = json_decode( $in, TRUE ) ) throw new \Exception( "Error on decode update json in ".__FILE__." on line ".__LINE__ );
            $up = $this->translateToUpdate( ( new TelegramUpdate() )->fromJSONArray( $json ) );
            self::$updates->setUpdate( $up );
        }
        
        return self::$updates;
    }
    
    private function translateToUpdate( \org\telegram\data\bot\Update $tup )
    {
        $up = ( new Update() )->setId( $tup->getUpdate_id() )->setContext( self::CONTEXT );
        $up->setChat( ( new Chat() )->setContext( self::CONTEXT ) );
        $this->fromCallbackQueryType( $tup->getCallback_query(), $up );
        $this->fromMessageType( $tup->getMessage(), $up );
        
        return $up;
    }
    
    
    private function fromCallbackQueryType( \org\telegram\data\bot\CallbackQueryType $cbq = NULL, Update $up )
    {
        if( !$cbq ) return NULL;
        $com = ( new Command() )->setName( $cbq->getData() );
        $this->fromUserType( $cbq->getFrom(), $up->getChat() );
        $up->setCommand( $com );
    }
    
    private function fromUserType( \org\telegram\data\bot\UserType $from = NULL, Chat $chat )
    {
        if( !$from ) return NULL;
        $u = ( new User() )
            ->setId( $from->getId() )
            ->setFirstName( $from->getFirst_name() )
            ->setLastName( $from->getLast_name() )
            ->setNickname( $from->getUsername() );
        $chat->setId( $from->getId() );
        $chat->setUser( $u );
    }
    
    
    private function fromMessageType( \org\telegram\data\bot\MessageType $mt = NULL, Update $up )
    {
        if( !$mt ) return NULL;
        $m = ( new Message() )
            ->setId( $mt->getMessage_id() )
            ->setDt( $mt->getDate() )
            ->setText( $mt->getText() );
        $this->fromLocationType( $mt->getLocation(), $m, $up->getChat() );
        $this->fromContactType( $mt->getContact(), $m, $up->getChat() );
        $this->fromUserType( $mt->getFrom(), $up->getChat() );
        $this->fromEntities( $mt->getEntities(), $m, $up );
        $this->fromChatType( $mt->getChat(), $up->getChat() );
        $m->setUser( $up->getChat()->getUser() );
        $up->setMessage( $m );
    }
        
    private function fromEntities( array $entities, Message $m, Update $up )
    {
        foreach( $entities as $entity )
        {
            if( $entity->getType() === "bot_command" && $m->getText() ) {
                $name = substr( $m->getText(), intval( $entity->getOffset() ) + 1, intval( $entity->getOffset() ) + intval( $entity->getLength() ) );
                $arg = substr( $m->getText(), intval( $entity->getOffset() ) + 1 + intval( $entity->getLength() ) );
                if( !$com = $up->getCommand() ) $com = new Command();
                $com->setName( $name )->setArguments( $arg );
                $up->setCommand( $com );
                
                return ;
            }
        }
    }

    private function fromLocationType( \org\telegram\data\bot\LocationType $lt = NULL, Message $m, Chat $chat )
    {
        if( !$lt ) return NULL;
        $l = ( new Location() )
            ->setLatitude( $lt->getLatitude() )
            ->setLongitude( $lt->getLongitude() );
        
        $chat->setLocation( $l );
        $m->setLocation( $l );
    }
    
    private function fromContactType( \org\telegram\data\bot\ContactType $ct = NULL, Message $m, Chat $chat )
    {
        if( !$ct ) return NULL;
        $c = ( new Contact() )
            ->setPhoneNumber( str_replace( ["+","(",")","[","]","-"], "", $ct->getPhone_number() ) );
        $chat->setContact( $c );
        $m->setContact( $c );
    }
    
    private function fromChatType( \org\telegram\data\bot\ChatType $cht, Chat $chat )
    {
        if( !$cht ) return NULL;
        $chat->setId( $cht->getId() )
            ->setType( $cht->getType() )
            ->setUser( ( new User() )
                ->setFirstName( $cht->getFirst_name() )
                ->setLastName( $cht->getLast_name() )
                ->setNickname( $cht->getUsername() )
                ->setId( $cht->getId() ) 
            );
    }
    
    /**
    public function getUpdates()
    {
        if( NULL == self::$updates ) {
            $in = file_get_contents( "php://input" );
            
            self::$updates = ( new \com\servandserv\data\bot\Updates() )->setContext( self::CONTEXT );
            if( !$json = json_decode( $in, TRUE ) ) throw new \Exception( "Error on decode update json in ".__FILE__." on line ".__LINE__ );
            $up = ( new \com\servandserv\data\bot\Update() )->setContext( self::CONTEXT );
            $chat = new \com\servandserv\data\bot\Chat();
            if( isset( $json["update_id"] ) ) $up->setId( $json["update_id"] );
            if( isset( $json["message"] ) ) {
                $up->setEvent( "MessageReceived" );
                $m = new \com\servandserv\data\bot\Message();
                if( isset( $json["message"]["message_id"] ) ) $m->setId( $json["message"]["message_id"] );
                if( isset( $json["message"]["date"] ) ) $m->setDt( $json["message"]["date"] );
                if( isset( $json["message"]["chat"] ) ) {
                    $chat = $this->chatFromJSON( $json["message"]["chat"] );
                }
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
    */
    
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