<?php

namespace com\servandserv\bot\domain\model\events;

class DebugEvent extends StoredEvent {

    public function __construct($text) {
        $this->text = $text;
        $this->occuredOn = intval(microtime(true) * 1000);
        $this->type = "DebugEvent";
    }

    public function appendLog($log) {
        $this->text .= PHP_EOL . $log;
        return $this;
    }

    public function getBody() {
        return $this->text;
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
