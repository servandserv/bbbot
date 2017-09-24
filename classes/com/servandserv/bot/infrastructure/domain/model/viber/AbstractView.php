<?php

namespace com\servandserv\bot\infrastructure\domain\model\viber;

use \com\servandserv\bot\domain\model\View;

abstract class AbstractView implements View
{

    protected $token;

    abstract public function getRequests();
    
    public function setToken( $token )
    {
        $this->token = $token;
    }
    
    protected function toJSON( $xmlstr, $templ )
    {
        $str = $this->transform( $xmlstr, $templ );
        if( !$json = json_decode( $str, TRUE ) ) throw new \Exception( "Error on json in template ".$templ );
        return $json;
    }
    
    protected function transform( $xmlstr, $templ )
    {
        $xml = new \DOMDocument();
        $xml->loadXML( $xmlstr );
        $xsl = new \DOMDocument( "1.0", "UTF-8" );
        $xsl->loadXML( file_get_contents( $templ ) );
        $xsl->documentURI = $templ;
        $xslProc = new \XSLTProcessor();
        $xslProc->importStylesheet( $xsl );
        
        return $xslProc->transformToXML( $xml );
    }
}