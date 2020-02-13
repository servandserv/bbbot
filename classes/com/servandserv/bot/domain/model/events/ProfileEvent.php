<?php

namespace com\servandserv\bot\domain\model\events;

class ProfileEvent extends StoredEvent {

    protected $action;

    public function __construct($action) {
        $this->action = $action;
        $this->occuredOn = intval(microtime(true) * 1000);
        $this->type = "ProfileEvent";
    }

    public function getBody() {
        return $this->action;
    }

    public function toReadableStr() {
        return $this->getBody();
    }

    public function bodyFromXmlReader(\XMLReader &$xr) {
        $this->text = $xr->readString();
    }

    public function bodyToXmlWriter(\XMLWriter &$xw) {
        $xw->text($this->text);
    }

}
