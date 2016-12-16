<?php

namespace com\servandserv\Bot\Domain\Model;

interface CurlClient
{
    public function request( $method, $command, $body );
    public function getBody();
}