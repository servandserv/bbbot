<?php

namespace com\servandserv\bot\domain\model\events;

use \com\servandserv\happymeal\XMLAdaptor;
use \com\servandserv\happymeal\xml\schema\AnyType;
use \com\servandserv\happymeal\errors\Error;
use \com\servandserv\happymeal\errors\Errors;

class ExceptionOccuredEvent extends StoredEvent {

    public function __construct(\Exception $e) {
        $errors = new Errors();
        do {
            // соберем все что можно
            $errors->setError(( new Error())
                            ->setDescription($e->getMessage() . " in " . $e->getFile() . " on " . $e->getLine() . ":" . PHP_EOL . $e->getTraceAsString())
            );
        } while ($e = $e->getPrevious());
        $this->any = $errors;
        $this->occuredOn = intval(microtime(true) * 1000);
        $this->type = "ExceptionOccuredEvent";
    }

    public function getBody() {
        return $this->any;
    }

    public function toReadableStr() {
        return $this->any->toXmlStr();
    }

    public function bodyFromXmlReader(\XMLReader &$xr) {
        $any = $this->any;
        if ($xr->localName == $any::ROOT && $xr->namespaceURI == $any::NS) {
            $this->any->fromXmlReader($xr);
        }
    }

    public function bodyToXmlWriter(\XMLWriter &$xw) {
        $any = $this->any;
        $xw->startElementNS(NULL, $any::ROOT, $any::NS);
        $any->toXmlWriter($xw, $any::ROOT, $any::NS, XMLAdaptor::CONTENTS);
        $xw->endElement();
    }

}
