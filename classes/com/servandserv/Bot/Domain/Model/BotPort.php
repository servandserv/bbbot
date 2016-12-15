<?php

namespace com\servandserv\Bot\Domain\Model;

use \com\servandserv\happymeal\XML\Schema\AnyType;

interface BotPort
{
    /**
     * @return \com\servandserv\data\bot\Updates
     */
    public function getUpdates();
    public function makeRequest( $name, array $args, callable $cb );
    public function response( AnyType $anyType = NULL, $code = 200 );
}