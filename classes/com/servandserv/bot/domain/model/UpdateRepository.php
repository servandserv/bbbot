<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\data\bot\Update;

interface UpdateRepository
{
    public function register( Update $up );
    public function registerCommand( Update $up );
    public function archive( $autoid, Update $up );
    public function findAllActive();
}