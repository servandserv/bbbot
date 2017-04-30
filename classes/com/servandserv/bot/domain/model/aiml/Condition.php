<?php

namespace com\servandserv\bot\domain\model\aiml;

class Condition
{

    private $name;
    private $li = [];
    private $text;
    
    public function getText( array $vars = [] )
    {
        if( isset( $vars[$this->name] ) && isset( $this->li[$vars[$this->name]] ) ) {
            return $this->li[$vars[$this->name]];
        } else {
            return $this->text;
        }
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
    
    public function setLi( $key, $text )
    {
        if( $key ) {
            $this->li[$key] = $text;
        } else {
            $this->text = $text;
        }
    }
    
}