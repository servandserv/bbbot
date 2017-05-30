<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\data\bot\Request;
use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\Delivery;
use \com\servandserv\data\bot\Read;

interface RequestRepository
{
    public function register( Request $req );
    public function findBySignature( $signature );
    public function delivery( Chat $chat, Delivery $del );
    public function read( Chat $chat, Read $read );
}