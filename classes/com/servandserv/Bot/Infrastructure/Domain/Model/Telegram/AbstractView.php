<?php

namespace com\servandserv\Bot\Infrastructure\Domain\Model\Telegram;

abstract class AbstractView implements \com\servandserv\Bot\Domain\Model\View
{

    const LOCATION = "\xF0\x9F\x93\x8D";
    const PHONE = "\xF0\x9F\x93\x9E";
    const HELP = "\xE2\x9D\x93";
    
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
    
    protected function getMarkup( array $btns = [] )
    {
        $keyboard = [
            [
                [ "text"=>self::HELP ],
                [ "text"=>self::LOCATION, "request_location"=>TRUE ],
                [ "text"=>self::PHONE, "request_contact"=>TRUE ]
            ]
        ];
        $markup = [
            "keyboard" => $keyboard, 
            "resize_keyboard" =>TRUE
        ];
        
        return json_encode( $markup, TRUE );
    }
}