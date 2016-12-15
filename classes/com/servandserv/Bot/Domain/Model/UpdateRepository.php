<?php

namespace com\servandserv\Bot\Domain\Model;

interface UpdateRepository
{
    public function register( \com\servandserv\data\bot\Update $up );
}