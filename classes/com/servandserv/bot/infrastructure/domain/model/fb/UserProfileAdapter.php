<?php

namespace com\servandserv\bot\infrastructure\domain\model\fb;

use \com\servandserv\bot\domain\model\UserProfilePort;
use \com\servandserv\bot\domain\model\CurlClient;

use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\User;
use \com\servandserv\data\curl\Request;

class UserProfileAdapter implements UserProfilePort
{

    protected $cli;
    protected $secret;

    public function __construct( CurlClient $cli, $secret )
    {
        $this->cli = $cli;
        $this->secret = $secret;
    }

    public function findUser( Chat $chat )
    {
        try {
            $req = ( new Request() )
                ->setMethod( "GET" )
                ->setQuery( $chat->getId()."?fields=first_name,last_name,locale,timezone,gender&access_token=".$this->secret );
            $resp = $this->cli->request( $req );
            $json = json_decode( $resp->getBody(), TRUE );
            $user = new User();
            if( isset( $json["first_name"] ) ) $user->setFirstName( $json["first_name"] );
            if( isset( $json["last_name"] ) ) $user->setLastName( $json["last_name"] );
            if( isset( $json["gender"] ) ) $user->setGender( strtoupper( substr( $json["gender"], 0, 1 ) ) );
            
            return $user;
        } catch( \Exception $e ) {
            trigger_error( $resp->getBody() );
        }
    }
}