<?php

namespace com\servandserv\bot\infrastructure\domain\model;

use \com\servandserv\bot\infrastructure\persistence\pdo\Repository as PDORepository;
use \com\servandserv\bot\domain\model\ChatRepository as ChatRepositoryInterface;
use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\Location;
use \com\servandserv\data\bot\Contact;
use \com\servandserv\data\bot\User;
use \com\servandserv\data\bot\Command;

class ChatRepository extends PDORepository implements ChatRepositoryInterface {

    const DESC = "DESC";
    const ASC = "ASC";
    const START = 0;
    const COUNT = 100;

    public function findByKeys(array $keys) {
        $chat = NULL;
        $params = [
            ":entityId" => $this->getEntityId($keys)
        ];
        //$query = "SELECT `ch`.*, `u`.* FROM `nchats` AS `ch`";
        //$query .= " LEFT JOIN `nusers` AS `u` ON `u`.`entityId`=`ch`.`entityId`";
        //$query .= " WHERE `ch`.`entityId`=:entityId;";
        $query = "SELECT * FROM `nchats` WHERE `entityId`=:entityId;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        while ($row = $sth->fetch()) {
            $chat = $this->chatFromRow($row);
        }

        return $chat;
    }

    /**
     * ищем чаты по номеру телефона, возвращаем массив чатов или null если ничего не найдено
     * @param type $phoneNumber
     * @return type mixed
     */
    public function findByPhoneNumber($phoneNumber) {
        $chats = [];
        $params = [":phoneNumber" => str_replace("+", "", $phoneNumber)];
        $query = "SELECT ch.* FROM `nchats` AS `ch` JOIN `ncontacts` AS `c` ON `ch`.`entityId`=`c`.`entityId` WHERE `c`.`phoneNumber`=:phoneNumber;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        while ($row = $sth->fetch()) {
            $chats[] = $this->chatFromRow($row);
        }
        return empty($chats) ? null : $chats;
    }

    /**
     * ищем чаты по uid пользователя, возвразаем null или массив чатов
     * @param type $uid
     * @return type
     */
    public function findByUID($uid) {
        $chats = [];
        $params = [":UID" => $uid];
        $query = "SELECT * FROM `nchats` WHERE `UID`=:UID;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        while ($row = $sth->fetch()) {
            $chats[] = $this->chatFromRow($row);
        }
        return empty($chats) ? null : $chats;
    }

    public function findAll() {
        $chats = [];
        $params = [];
        $query = "SELECT * FROM `nchats` ORDER BY `updated` DESC;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        while ($row = $sth->fetch()) {
            $chats[] = $this->chatFromRow($row);
        }
        return $chats;
    }

    public function locationsFor(Chat $chat) {
        $params = [":entityId" => $this->getEntityId([$chat->getId(), $chat->getContext()])];
        $query = "SELECT * FROM `nlocations` WHERE `entityId`=:entityId ORDER BY `updated` DESC;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        while ($row = $sth->fetch()) {
            $loc = new Location();
            $loc->fromMarkupArray($row);
            $chat->setLocation($loc);
        }

        return $chat;
    }

    public function contactsFor(Chat $chat) {
        $params = [":entityId" => $this->getEntityId([$chat->getId(), $chat->getContext()])];
        $query = "SELECT * FROM `ncontacts` WHERE `entityId`=:entityId ORDER BY `updated` DESC;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        while ($row = $sth->fetch()) {
            $loc = new Contact();
            $loc->fromMarkupArray($row);
            $chat->setContact($loc);
        }

        return $chat;
    }

    public function commandsFor(Chat $chat, $order = self::DESC, $start = self::START, $count = self::COUNT) {
        $params = [":entityId" => $this->getEntityId([$chat->getId(), $chat->getContext()])];
        $limit = "";
        if ($count)
            $limit = " LIMIT $start," . strval($start + $count);
        $query = "SELECT * FROM `ncommands` WHERE `entityId`=:entityId ORDER BY `updated` $order $limit;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        $coms = [];
        while ($row = $sth->fetch()) {
            $com = new Command();
            $com->fromMarkupArray($row);
            $chat->setCommand($com);
        }

        return $chat;
    }

    public function remove(Chat $chat) {
        $params = [":entityId" => $this->getEntityId([$chat->getId(), $chat->getContext()])];
        $query = "delete from `nchats` where `entityId`=:entityId;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        $query = "delete from `ncontacts` where `entityId`=:entityId;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        $query = "delete from `nsubscriptions` where `entityId`=:entityId;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
        $query = "delete from `nlocations` where `entityId`=:entityId;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
    }

    public function bindOuterUID(array $keys, $uid, $login, $level) {
        $params = [
            ":entityId" => $this->getEntityId($keys),
            ":UID" => $uid,
            ":outerName" => $login,
            ":securityLevel" => $level
        ];

        $query = "UPDATE `nchats` SET `UID`=:UID, `outerName`=:outerName, `securityLevel`=:securityLevel WHERE `entityId`=:entityId;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
    }

    public function unbindOuterUID(Chat $chat) {
        $params = [
            ":entityId" => $this->getEntityId([$chat->getId(), $chat->getContext()])
        ];

        $query = "UPDATE `nchats` SET `UID` = NULL, `outerName`=NULL, `securityLevel`=NULL WHERE `entityId`=:entityId;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
    }

    public function updateChatUser(Chat $chat) {
        $entityId = $this->getEntityId([$chat->getId(), $chat->getContext()]);
        $params[":entityId"] = $entityId;
        $params[":firstName"] = $chat->getUser()->getFirstName();
        $params[":lastName"] = $chat->getUser()->getLastName();

        $user = [
            ":entityId" => $entityId,
            ":firstName" => $chat->getUser()->getFirstName(),
            ":lastName" => $chat->getUser()->getLastName(),
            ":middleName" => $chat->getUser()->getMiddleName(),
            ":gender" => $chat->getUser()->getGender(),
            ":locale" => $chat->getUser()->getLocale()
        ];

        $query = "";
        foreach ($user as $col => $val) {
            $query .= ",`" . substr($col, 1) . "`=" . $col;
        }
        $query = "INSERT INTO `nusers` SET " . substr($query, 1) . " ON DUPLICATE KEY UPDATE " . substr($query, 1);
        $sth = $this->conn->prepare($query);
        $sth->execute($user);

        $query = "UPDATE `nchats` SET `firstName`=:firstName, `lastName`=:lastName WHERE `entityId`=:entityId;";
        $sth = $this->conn->prepare($query);
        $sth->execute($params);
    }

    private function chatFromRow(array $row) {
        $chat = new Chat();
        $chat->fromMarkupArray($row);
        $user = new User();
        $chat->setUser($user->fromMarkupArray($row));
        if ($row["latitude"] && $row["longitude"]) {
            $loc = new Location();
            $chat->setLocation($loc->fromMarkupArray($row));
        }
        if ($row["phoneNumber"]) {
            $c = new Contact();
            $chat->setContact($c->fromMarkupArray($row));
        }

        return $chat;
    }

}
