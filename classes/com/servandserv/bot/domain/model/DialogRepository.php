<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\Dialog as DialogTDO;

interface DialogRepository
{
    public function register( DialogTDO $dia );
    public function findForChat( Chat $chat, DialogTDO $impl );
}