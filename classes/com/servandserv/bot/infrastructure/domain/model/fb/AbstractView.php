<?php

namespace com\servandserv\bot\infrastructure\domain\model\fb;

use \com\servandserv\bot\domain\model\View;

abstract class AbstractView implements View
{

    abstract public function getRequests();
    
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