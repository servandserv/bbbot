<?php

namespace com\servandserv\bot\infrastructure\http;

use \com\servandserv\bot\domain\model\CurlClient;
use \com\servandserv\bot\domain\model\CurlException;
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
        try {
            $this->resp = $this->cli->request( $req->getMethod(), $req->getQuery(), [ "headers" => $headers, "body" => $req->getContent() ] );
            return $this;
        } catch( \Exception $e ) {
            throw new CurlException( $e->getMessage(), $e->getResponse()->getStatusCode(), $e );
        }
    }
    
    public function getEffectiveUrl( $url, $max = 1 )
    {
        $res = $this->cli->request( "GET", $url, [
            "allow_redirects" => [
                "max" => $max,             // allow at most 10 redirects.
                "strict" => true,      // use "strict" RFC compliant redirects.
                "referer" => true,      // add a Referer header
                "protocols" => ["https"], // only allow https URLs
                "track_redirects" => true
            ]
        ] );
        if( $redirect = $res->getHeaderLine("X-Guzzle-Redirect-History") ) {
            return $redirect;
        } else {
            return $url;
        }
    }
    
    public function getBody()
    {
        if( $this->resp ) {
            return $this->resp->getBody();
        }
        return NULL;
    }
}