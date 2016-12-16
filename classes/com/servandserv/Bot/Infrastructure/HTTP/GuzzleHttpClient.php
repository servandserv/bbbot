<?php

namespace com\servandserv\Bot\Infrastructure\HTTP;

use \com\servandserv\Bot\Domain\Model\CurlClient;
use \com\servandserv\data\curl\Request;

class GuzzleHttpClient implements CurlClient
{
    protected $cli;
    protected $resp;
    
    public function __construct( \GuzzleHttp\Client $cli )
    {
        $this->cli = $cli;
    }
    
    public function request( Request $req )
    {
        $headers = [];
        foreach( $req->getHeader() as $header ) {
            $headers[$header->getName()] = $header->getValue();
        }
        $this->resp = $this->cli->request( $req->getMethod(), $req->getQuery(), [ "headers" => $headers, "body" => $req->getContent() ] );
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