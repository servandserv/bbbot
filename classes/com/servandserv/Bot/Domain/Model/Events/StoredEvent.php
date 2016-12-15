<?php

namespace com\servandserv\Bot\Domain\Model\Events;

abstract class StoredEvent implements Event
{

    const ROOT = "Event";
    const NS = "urn:com:servandserv:bbbot:data:Event";

    protected $occuredOn;
    protected $type;
    protected $id;

    public function occuredOn() { return $this->occuredOn; }
    public function id() { return $this->id; }
    public function type() { return $this->type; }
    public function setId( $id ) { $this->id = $id; return $this; }
    
    public function toXmlStr()
    {
        $xw = new \XMLWriter();
		$xw->openMemory();
		$xw->setIndent( TRUE );
		$xw->startDocument( "1.0", "UTF-8" );
		$xw->startElementNS( NULL, self::ROOT, self::NS );
		$xw->writeAttribute( "id", $this->id() );
		$xw->writeAttribute( "type", $this->type() );
		$xw->writeAttribute( "occuredOn", $this->occuredOn() );
		$this->bodyToXmlWriter( $xw );
		$xw->endElement();
		$xw->endDocument();
		
		return $xw->flush();
    }
    
    public function fromXmlStr( $xmlstr )
    {
        $xr = new \XMLReader();
        $xr->XML( $xmlstr );
        while ( $xr->nodeType != \XMLReader::ELEMENT ) $xr->read();
		$root = $xr->localName;
		if( $xr->hasAttributes ) {
			while( $xr->moveToNextAttribute() ) {
			    switch( $xr->localName ) {
			        case "id":
			            $this->id = $xr->value;
			            break;
			        case "type":
			            $this->type = $xr->value;
			            break;
			        case "occuredOn":
			            $this->occuredOn = $xr->value;
			        break;
			    }
			}
			$xr->moveToElement();
	    }
		if ( $xr->isEmptyElement ) return $this;
		while ( $xr->read() ) {
			if ( $xr->nodeType == \XMLReader::ELEMENT ) {
				$xsinil = $xr->getAttributeNs( "nil", "http://www.w3.org/2001/XMLSchema-instance" ) == "true";
				$this->bodyFromXmlReader( $xr );
			} elseif ( $xr->nodeType == \XMLReader::END_ELEMENT && $root == $xr->localName ) {
				return $this;
			}
		}
		return $this;
    }
    
    abstract protected function bodyToXmlWriter( \XMLWriter &$xw );
    abstract protected function bodyFromXmlReader( \XMLReader &$xr );
}