<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\data\bot\Message;

interface MessageRepository {

    public function findAllActive();

    public function register(Message $msg);
}
