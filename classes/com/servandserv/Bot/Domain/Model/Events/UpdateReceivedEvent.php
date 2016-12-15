<?php

namespace com\servandserv\Bot\Domain\Model\Events;

use \com\servandserv\data\bot\Update;
use \com\servandserv\happymeal\XMLAdaptor;

class UpdateReceivedEvent extends StoredEvent
{
    public function __construct( Update $update = NULL )
    {
        $this->update = $update;
        $this->occuredOn = intval( microtime( true ) * 1000 );
        $this->type = "UpdateReceivedEvent";
    }
    
    public function getUpdate()
    {
        return $this->update;
    }
    
    public function bodyFromXmlReader( \XMLReader &$xr )
    {
        $up = new Update();
        if( $xr->localName == $up::ROOT && $xr->namespaceURI == $up::NS ) {
            $up->fromXmlReader( $xr );
            $this->update = $up;
        }
    }
    
    public function bodyToXmlWriter( \XMLWriter &$xw )
    {
        $xw->startElementNS( NULL, $this->update::ROOT, $this->update::NS );
        $this->update->toXmlWriter( $xw, $this->update::ROOT, $this->update::NS, XMLAdaptor::CONTENTS );
        $xw->endElement();
    }
}