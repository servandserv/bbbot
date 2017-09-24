<?php

namespace com\servandserv\bot\domain\model\aiml;

class Condition
{

    private $name;
    private $li = [];
    private $text;
    
    public function getText( array $vars = [] )
    {
        // condition variable in memory
        $var = isset( $vars[$this->name] ) ? $vars[$this->name] : NULL;
        foreach( $this->li as $li ) {
            if( $li[1] == $var ) return $li[0];
            elseif( $li[2] !== NULL && preg_match( $li[2], $var, $m ) ) return $li[0];
        }
        
        return $this->text;
    }
    
    public function getName()
    {
        return $this->name;
    }
    
    public function setName( $name )
    {
        $this->name = $name;
        return $this;
    }
    
    public function getLi()
    {
        return $this->li;
    }
    
    public function setLi( $text, $value = NULL, $pattern = NULL )
    {
        if( $value!==NULL || $pattern!==NULL ) {
            $this->li[] = [ $text, $value, $pattern ];
        } else {
            $this->text = $text;
        }
    }
    
}