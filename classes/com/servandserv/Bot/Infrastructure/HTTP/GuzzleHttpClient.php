<?php

namespace com\servandserv\Bot\Infrastructure\HTTP;

use \com\servandserv\Bot\Domain\Model\CurlClient;

class GuzzleHttpClient implements CurlClient
{
    protected $cli;
    protected $resp;
    
    public function __construct( \GuzzleHttp\Client $cli )
    {
        $this->cli = $cli;
    }
    
    public function request( $method, $command, array $args )
    {
        $this->resp = $this->cli->request( $method, $command, $args );
        return $this;
    }
    
    public function getBody()
    {
        if( $this->resp ) {
            return $this->resp->getBody();
        }
        return NULL;
    }
}