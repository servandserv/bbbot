<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\data\bot\Dialog as DialogTDO;
use \com\servandserv\data\bot\Interchange;
use \com\servandserv\data\bot\Variable;

class Dialog extends DialogTDO
{
    public function getLastAnswer()
    {
        $history = $this->getInterchange();
        $lastanswer = NULL;
        if( !empty( $history ) ) {
            $lastanswer = end( $history )->getAnswer();
        }
        
        return $lastanswer;
    }
    
    public function varsToAssocArray()
    {
        $vars = [];
        foreach( $this->getVariable() as $var ) {
            $vars[$var->getName()] = $var->getValue(); 
        }
        
        return $vars;
    }
    
    public function varsFromAssocArray( array $vars )
    {
        $this->setVariableArray([]);//обнулим потому что некоторые переменные могут быть переопределены в ответах клиента
        foreach( $vars as $k=>$v ) {
            $this->setVariable( ( new Variable() )->setName( $k )->setValue( $v ) );
        }
        
        return $this;
    }
    
    public function createInterchange( $question, $answer )
    {
        return ( new Interchange() )
            ->setCreated( time() )
            ->setQuestion( $question )
            ->setAnswer( $answer );
    }
    
    public function getLog()
    {
        $log = "";
        $ichs = $this->getInterchange();
        foreach( $ichs as $ich ) {
            $log .= "Q: ".$ich->getQuestion()."\n";
            $log .= "A: ".$ich->getAnswer()."\n";
        }
        
        return $log;
    }
}