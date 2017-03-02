<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\data\bot\Request;
use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\Delivery;

interface RequestRepository
{
    public function register( Request $req );
    public function delivery( Chat $chat, Delivery $del );
}