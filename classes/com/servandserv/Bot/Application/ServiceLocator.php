<?php

namespace com\servandserv\Bot\Application;

interface ServiceLocator
{
    public function get( $prop, array $args = [] );
    public function create( $cl, array $args = [], callable $cb = NULL );
}