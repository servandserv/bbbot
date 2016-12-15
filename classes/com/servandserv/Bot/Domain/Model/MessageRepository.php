<?php

namespace com\servandserv\Bot\Domain\Model;

interface MessageRepository
{
    public function findAllActive();
    public function register( \com\servandserv\data\bot\Message $msg );
}