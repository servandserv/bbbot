<?php

namespace com\servandserv\Bot\Domain\Model;

use \com\servandserv\data\curl\Request;

interface CurlClient
{
    public function request( Request $req );
    public function getBody();
}