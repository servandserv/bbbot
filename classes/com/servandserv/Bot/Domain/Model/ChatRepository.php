<?php

namespace com\servandserv\Bot\Domain\Model;

use \com\servandserv\data\bot\Chat;

interface ChatRepository
{
    public function findByKeys( array $keys );
    public function findByPhoneNumber( $phoneNumber );
    public function findByUID( $uid );
    public function findAll();
    public function locationsFor( Chat $chat );
    public function contactsFor( Chat $chat );
    public function commandsFor( Chat $chat );
}