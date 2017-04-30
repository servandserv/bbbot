<?php

namespace com\servandserv\bot\domain\model\aiml;

class Template
{
    const RANDOM_REG = "/<random[^>]*>(.*)<\/random>/";
    const CONDITION_REG = "/<condition[^>]*name=\"(.*)\"[^>]*>(.*)<\/condition>/";
    
    private $text;
    private $random;
    private $condition;
    
    public function getTemplateText( array $vars = [] )
    {
        if( $this->random ) {
            return $this->random->getText( $vars );
        } elseif( $this->condition ) {
            return $this->condition->getText( $vars );
        } else {
            return $this->getText( $vars );
        }
    }
    
    public function getText()
    {
        return $this->text;
    }
    
    public function getRandom()
    {
        return $this->random;
    }
    
    public function getCondition()
    {
        return $this->condition;
    }
    
    public function setText( $text )
    {
        $this->text = $text;
        return $this;
    }
    
    public function setRandom( Random $random )
    {
        $this->random = $random;
        return $this;
    }
    
    public function setCondition( Condition $condition )
    {
        $this->condition = $condition;
        return $this;
    }
}