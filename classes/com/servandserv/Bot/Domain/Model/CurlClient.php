<?php

namespace com\servandserv\Bot\Domain\Model;

interface CurlClient
{
    public function request( $method, $command, array $params );
    public function getBody();
}