<?php

namespace com\servandserv\Bot\Infrastructure\Domain\Model\Telegram;

use \com\servandserv\Bot\Domain\Model\UserNotFoundException;
use \com\servandserv\Bot\Domain\Model\CurlClient;
use \com\servandserv\Bot\Domain\Model\CurlException;
use \com\servandserv\Bot\Domain\Model\Events\Publisher;
use \com\servandserv\Bot\Domain\Model\Events\MessengerErrorOccuredEvent;
use \com\servandserv\Bot\Domain\Service\Synchronizer;
use \org\telegram\data\bot\Update as TelegramUpdate;
use \com\servandserv\data\bot\Updates;
use \com\servandserv\data\bot\Update;
use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\User;
use \com\servandserv\data\bot\Message;
use \com\servandserv\data\bot\Location;
use \com\servandserv\data\bot\Contact;
use \com\servandserv\data\bot\Command;
use \com\servandserv\data\bot\UpdateEventType;

class BotAdapter implements \com\servandserv\Bot\Domain\Model\BotPort
{
    const CONTEXT = "org.telegram";

    protected $cli;
    protected $NS;
    protected $syn;
    protected $messagesPerSecond;
    protected static $updates;

    public function __construct( CurlClient $cli, $NS, Synchronizer $syn )
    {
        $this->cli = $cli;
        $this->NS = $NS;
        $this->syn = $syn;
    }
    
    public function makeRequest( $name, array $args, callable $cb = NULL )
    {
        //$timer = \com\servandserv\Bot\Domain\Service\Timer::getInstance( intval( self::LIMIT_PER_SECOND *0.75 ) );
        
        $clName = $this->NS."\\".$name;
        if( !class_exists( $clName ) ) throw new \Exception( "Class for VIEW name \"$name\" not exists." );
        $cl = new \ReflectionClass( $clName );
        $view = call_user_func_array( array( &$cl, 'newInstance' ), $args );
        
        try {
            $requests = $view->getRequests();
            foreach( $requests as $request ) {
                $this->syn->next( self::CONTEXT );// следующая отправка
                $watermark = intval( microtime( true ) * 1000 );
                
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
            //trigger_error(print_r(json_decode($request->getContent()),true));
        } catch( CurlException $e ) {
            $str = isset( $request ) ? $request->getContent() : "";
            switch( $e->getCode() ) {
                case "401":
                case "403":
                    throw new UserNotFoundException( $e->getMessage().":".$str, $e->getCode() );
                    break;
                default:
                    throw new \Exception( $e->getMessage().":".$str, $e->getCode() );
            }
        }
    }
    
    public function getUpdates()
    {
        if( NULL == self::$updates ) {
            self::$updates = ( new Updates() )->setContext( self::CONTEXT );
            $in = file_get_contents( "php://input" );
            if( !$json = json_decode( $in, TRUE ) ) throw new \Exception( "Error on decode update json in ".__FILE__." on line ".__LINE__. "\n input $in" );
            $up = $this->translateToUpdate( ( new TelegramUpdate() )->fromJSONArray( $json ) );
            self::$updates->setUpdate( $up );
        }
        
        return self::$updates;
    }
    
    public function translateToUpdate( \org\telegram\data\bot\Update $tup )
    {
        $up = ( new Update() )->setId( $tup->getUpdate_id() )->setContext( self::CONTEXT );
        $up->setChat( ( new Chat() )->setContext( self::CONTEXT ) );
        $up->setEvent( UpdateEventType::_RECEIVED );
        $this->fromCallbackQueryType( $tup->getCallback_query(), $up );
        // various types of message
        $msg = NULL;
        if( $tup->getMessage() ) {
            $msg = $tup->getMessage();
        } elseif( $tup->getEdited_message() ) {
            $msg = $tup->getEdited_message();
        } elseif( $tup->getChannel_post() ) {
            $msg = $tup->getChannel_post();
        } elseif( $tup->getEdited_channel_post() ) {
            $msg = $tup->getEdited_channel_post();
        }
        if( $msg ) {
            $this->fromMessageType( $msg, $up );
        }
        
        return $up;
    }
    
    
    private function fromCallbackQueryType( \org\telegram\data\bot\CallbackQueryType $cbq = NULL, Update $up )
    {
        if( !$cbq ) return NULL;
        $com = ( new Command() )->setName( $cbq->getData() );
        $this->fromUserType( $cbq->getFrom(), $up->getChat() );
        $up->setCommand( $com );
        $up->setEvent( UpdateEventType::_POSTBACK );
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
            ->setDt( str_pad( $mt->getDate(), 13, "0" ) )
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
            ->setPhoneNumber( str_replace( ["+","(",")","[","]","-"], "", $ct->getPhone_number() ) )
            ->setUser( ( new User() )
                ->setId( $ct->getUser_id() )
                ->setFirstName( $ct->getFirst_name() )
                ->setLastName( $ct->getLast_name() )
            );
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
    
    public function response( \com\servandserv\happymeal\XML\Schema\AnyType $anyType = NULL, $code = 200 )
    {
        if( !headers_sent() ) {
            $protocol = ( isset( $_SERVER["SERVER_PROTOCOL"] ) ? $_SERVER["SERVER_PROTOCOL"] : "HTTP/1.0" );
            header( $protocol . " " . $code . " " . http_response_code( $code ) );
        }
        exit;
    }

}