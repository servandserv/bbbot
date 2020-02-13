<?php

namespace com\servandserv\bot\infrastructure\domain\model;

use \com\servandserv\bot\infrastructure\persistence\pdo\Repository as PDORepository;
use \com\servandserv\bot\domain\model\DialogRepository as DialogRepositoryInterface;
use \com\servandserv\data\bot\Dialog;
use \com\servandserv\data\bot\Chat;

class DialogRepository extends PDORepository implements DialogRepositoryInterface {

    private static $dialogs = []; // сессионный кэш для диалогов

    public function register(Dialog $dia) {
        if (!$dia->getCreated()) {
            // если новый диалог устанавливаем дату создания на сегодня
            $created = date("Y-m-d", time());
            $dia->setCreated($created);
        }
        $entityId = $this->getEntityId([$dia->getChat()->getId(), $dia->getChat()->getContext()]);
        static::$dialogs[$entityId] = $dia;
        $params = [
            ":entityId" => $entityId,
            ":created" => $dia->getCreated(),
            ":dialog" => $dia->toXmlStr()
        ];
        $query = "";
        foreach ($params as $col => $val) {
            $query .= ",`" . substr($col, 1) . "`=" . $col;
        }
        $query = "INSERT INTO `ndialogs` SET " . substr($query, 1) . " ON DUPLICATE KEY UPDATE " . substr($query, 1);
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
    }

    // актуальный диалог
    public function findForChat(Chat $chat, Dialog $impl) {
        $entityId = $this->getEntityId([$chat->getId(), $chat->getContext()]);
        if (isset(static::$dialogs[$entityId]))
            return static::$dialogs[$entityId];
        $impl->setChat($chat);
        $params = [
            ":entityId" => $entityId,
            ":created" => date("Y-m-d", time())
        ];
        $query = "SELECT `dialog` FROM `ndialogs` WHERE `entityId`=:entityId AND `created`=:created LIMIT 0,1;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        while ($row = $sth->fetch()) {
            $impl->fromXmlStr($row["dialog"]);
        }
        static::$dialogs[$entityId] = $impl;

        return $impl;
    }

}
