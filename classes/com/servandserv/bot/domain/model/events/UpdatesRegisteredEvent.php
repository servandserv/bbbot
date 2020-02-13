<?php

namespace com\servandserv\bot\domain\model\events;

use \com\servandserv\data\bot\Updates;

class UpdatesRegisteredEvent extends InMemoryEvent {

    public function __construct(Updates $updates, $context) {
        $this->updates = $updates;
        $this->context = $context;
        $this->occuredOn = intval(microtime(true) * 1000);
    }

    public function occuredOn() {
        return $this->occuredOn;
    }

    public function getUpdates() {
        return $this->updates;
    }

    public function getContext() {
        return $this->context;
    }

    public function toReadableStr() {
        return $this->updates->toXmlStr();
    }

}
