<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\data\bot\Chat;

interface ChatRepository {

    public function findByKeys(array $keys);

    public function findByPhoneNumber($phoneNumber);

    public function findByUID($uid);

    public function findAll();

    public function locationsFor(Chat $chat);

    public function contactsFor(Chat $chat);

    public function commandsFor(Chat $chat);

    public function bindOuterUID(array $keys, $uid, $login, $level);

    public function unbindOuterUID(Chat $chat);

    public function remove(Chat $chat);
}
