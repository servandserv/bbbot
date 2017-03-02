<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\happymeal\xml\schema\AnyType;

interface BotPort
{
    /**
     * @return \com\servandserv\data\bot\Updates
     */
    public function getUpdates();
    public function makeRequest( $name, array $args, callable $cb );
    public function response( AnyType $anyType = NULL, $code = 200 );
}