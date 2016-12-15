<?php

namespace com\servandserv\Bot\Domain\Model;

interface RequestRepository
{
    public function register( \com\servandserv\data\bot\Request $req );
    public function delivery( \com\servandserv\data\bot\Chat $chat, \com\servandserv\data\bot\Delivery $del );
}