<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\data\bot\Chat;

interface UserProfilePort
{
    /**
     * @return \com\servandserv\data\bot\User
     */
    public function findUser( Chat $chat );
}