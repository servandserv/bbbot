<?php

namespace com\servandserv\Bot\Domain\Model;

use \com\servandserv\data\bot\Update;

interface UpdateRepository
{
    public function register( Update $up );
    public function archive( $autoid, Update $up );
    public function findAllActive();
}