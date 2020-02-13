<?php

namespace com\servandserv\bot\infrastructure\domain\model;

use \com\servandserv\bot\infrastructure\persistence\pdo\Repository as PDORepository;
use \com\servandserv\bot\domain\model\events\EventStore as EventStoreInterface;
use \com\servandserv\bot\domain\model\events\StoredEvent;

class EventStore extends PDORepository implements EventStoreInterface {

    public function append(StoredEvent $event) {
        $event->setId($this->getEventId($event));
        $params = [
            ":id" => $event->id(),
            ":type" => $event->type(),
            ":occuredOn" => $event->occuredOn(),
            ":xmlstr" => $event->toXmlStr()
        ];
        $query = "";
        foreach ($params as $col => $val) {
            $query .= ",`" . substr($col, 1) . "`=" . $col;
        }
        $query = "INSERT INTO `nevents` SET " . substr($query, 1) . " ON DUPLICATE KEY UPDATE " . substr($query, 1);
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
    }

    public function allEventsSince($watermark) {

    }

    public function allEventsAfter($id) {

    }

    private function getEventId(StoredEvent $event) {
        return md5($event->toXmlStr());
    }

}
