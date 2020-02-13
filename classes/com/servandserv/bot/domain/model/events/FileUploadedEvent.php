<?php

namespace com\servandserv\bot\domain\model\events;

use \com\servandserv\data\bot\Link;
use \com\servandserv\happymeal\XMLAdaptor;

class FileUploadedEvent extends StoredEvent {

    public function __construct(Link $link) {
        $this->link = $link;
        $this->occuredOn = intval(microtime(true) * 1000);
        $this->type = "FileUploadedEvent";
    }

    public function getLink() {
        return $this->link;
    }

    public function toReadableStr() {
        return $this->link->toXmlStr();
    }

    public function bodyFromXmlReader(\XMLReader &$xr) {
        $link = new Link();
        if ($xr->localName == $link::ROOT && $xr->namespaceURI == $link::NS) {
            $link->fromXmlReader($xr);
            $this->link = $link;
        }
    }

    public function bodyToXmlWriter(\XMLWriter &$xw) {
        $link = $this->link;
        $xw->startElementNS(NULL, $link::ROOT, $link::NS);
        $this->link->toXmlWriter($xw, $link::ROOT, $link::NS, \XMLAdaptor::CONTENTS);
        $xw->endElement();
    }

}
