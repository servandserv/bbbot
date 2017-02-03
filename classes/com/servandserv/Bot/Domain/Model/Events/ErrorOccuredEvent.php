<?php

namespace com\servandserv\Bot\Domain\Model\Events;

use \com\servandserv\happymeal\XMLAdaptor;
use \com\servandserv\happymeal\XML\Schema\AnyType;

class ErrorOccuredEvent extends StoredEvent
{
    public function __construct( AnyType $any )
    {
        $this->any = $any;
        $this->occuredOn = intval( microtime( true ) * 1000 );
        $this->type = "ErrorOccuredEvent";
    }
    
    public function getBody()
    {
        return $this->any;
    }
    
    public function toReadableStr()
    {
        return $this->any->toXmlStr();
    }
    
    public function bodyFromXmlReader( \XMLReader &$xr )
    {
        $any = $this->any;
        if( $xr->localName == $any::ROOT && $xr->namespaceURI == $any::NS ) {
            $this->any->fromXmlReader( $xr );
        }
    }
    
    public function bodyToXmlWriter( \XMLWriter &$xw )
    {
        $any = $this->any;
        $xw->startElementNS( NULL, $any::ROOT, $any::NS );
        $any->toXmlWriter( $xw, $any::ROOT, $any::NS, XMLAdaptor::CONTENTS );
        $xw->endElement();
    }
}