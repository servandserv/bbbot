<?php

namespace com\servandserv\bot\domain\model\aiml;

class Random
{
    private $li=[];
    
    public function getText( array $vars = [] )
    {
        $text = $this->li[rand(0,count($this->li)-1)];
        return $text;
    }
    
    public function getLi()
    {
        return $this->li;
    }
    
    public function setLi( $text )
    {
        $this->li[] = $text;
    }
}