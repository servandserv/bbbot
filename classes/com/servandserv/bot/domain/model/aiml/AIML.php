<?php

namespace com\servandserv\bot\domain\model\aiml;

use \com\servandserv\bot\domain\service\FuzzyMean;
use \com\servandserv\data\bot\Dict;
use \com\servandserv\data\bot\Word;

class AIML
{

    const SRAI_REG = "/<srai[^>]+>(.*)<\/srai>/";
    const BOT_REG = "/<bot[^>]*name=\"(.*)\"[^>]*\/>/";
    const DATE_REG = "/<date[^>]*format=\"(.*)\"[^>]*\/>/";
    const GET_REG = "/<get[^>]*name=\"(.*)\"[^>]*\/>/";
    const SET_REG = "/<set[^>]*name=\"(.*)\"[^>]*>(.*)<\/set>/";

    private $category = [];
    private $env = [];// массив с переменными исполнения для замены в тегах bot
    private $vars = [];// массив с переменными диалога для замены в тегах get
    private $commands = [];// массив с командами для бота
    
    // достаем категории сортируя их в соответствии с 
    // фразой вопроса
    public function getCategory( $phrase = NULL, $history = NULL )
    {
        if( !$phrase ) {
            return $this->category;
        } else {
            $phrase = strtolower( $phrase );
            foreach( $this->category as &$cat ) {
                $cat->calcSimilarity( $phrase, $history );
            }
            usort( $this->category, function( $a, $b ) {
                // если коэффициент схожести одной записи отличается от другой
                $scoreA = $a->getScore();
                $scoreB = $b->getScore();
                if( $scoreA > $scoreB ) return -1;
                if( $scoreA < $scoreB ) return 1;
                if( $scoreA == 0 && count( $a->getTokens() ) > count( $b->getTokens() ) ) return -1;
                
                return 0;
            });
        
            return $this->category;
        }
    }
    
    public function answer( $question, $history = NULL, array $env = [], array $vars = [] )
    {
        $this->env = $env;
        $this->vars = $vars;
        $selected = $this->getCategory( $question, $history )[0];
        $this->vars["topic"] = $selected->getTopic();
        $templ = $selected->getTemplate( array_merge( $env, $vars ) );
        $templ = $this->filterSrai( $templ, $selected->getThat() );
        // подставляем переменные
        $templ = $this->filterDate( $templ );
        $templ = $this->filterBot( $templ );
        // применяем переменные диалога
        $templ = $this->filterGet( $templ );
        // устанавливаем переменные
        $templ = $this->filterSet( $templ );
        
        return $templ;
    }
    
    public function createInterchange( $question, $answer )
    {
        return ( new Interchange() )
            ->setCreated( time() )
            ->setQuestion( $question )
            ->setAnswer( $answer );
    }
    
    public function reset()
    {
        $this->vars = [];
        $this->commands = [];
    }
    
    public function getCommands()
    {
        return $this->commands;
    }
    
    public function getVars()
    {
        return $this->vars;
    }
    
    public function getCurrentTopic()
    {
        return isset( $this->vars["topic"] ) ? $this->vars["topic"] : "unknown";
    }
    
    public function setCategory( Category $cat )
    {
        $this->category[] = $cat;
    }
    
    private function filterSrai( $templ, $that )
    {
        if( preg_match( self::SRAI_REG, $templ, $m ) ) {
            if( isset( $m[1] ) ) {
                if( $srai = $this->searchSrai( strtolower( $m[1] ), $that ) ) {
                    $templ = $srai->getTemplate();
                }
            }
        }
        
        return trim( $templ );
    }
    
    private function searchSrai( $pattern, $that = NULL )
    {
        foreach( $this->category as $cat ) {
            if( $cat->getPattern() == $pattern && $cat->getThat() == $that ) {
                return $cat;
            }
        }
    }
    
    private function filterBot( $templ )
    {
        if( preg_match_all( self::BOT_REG, $templ, $m ) ) {
            foreach( $m[0] as $k=>$str ) {
                if( isset( $this->env[$m[1][$k]] ) ) {
                    $templ = str_replace( $str, $this->env[$m[1][$k]], $templ );
                } else {
                    $templ = str_replace( $str, "", $templ );
                }
            }
        }
        
        return trim( $templ );
    }
    
    private function filterDate( $templ )
    {
        if( preg_match_all( self::DATE_REG, $templ, $m ) ) {
            foreach( $m[0] as $k=>$str ) {
                if( isset( $this->env[$m[1][$k]] ) ) {
                    $templ = str_replace( $str, date( $this->env[$m[1][$k]] ), $templ );
                } else {
                    $templ = str_replace( $str, "", $templ );
                }
            }
        }
        
        return trim( $templ );
    }
    
    
    private function filterGet( $templ )
    {
        if( preg_match_all( self::GET_REG, $templ, $m ) ) {
            foreach( $m[0] as $k=>$str ) {
                if( isset( $this->vars[$m[1][$k]] ) ) {
                    $templ = str_replace( $str, $this->vars[$m[1][$k]], $templ );
                } else {
                    $templ = str_replace( $str, "", $templ );
                }
            }
        }
        
        return trim( $templ );
    }
    
    private function filterSet( $templ )
    {
        if( preg_match_all( self::SET_REG, $templ, $m ) ) {
            foreach( $m[0] as $k=>$str ) {
                switch( $m[1][$k] ) {
                    case "command":
                        $this->commands[] = $m[2][$k];
                        $this->vars["COMMAND"] = $m[2][$k];
                        break;
                    default:
                        $this->vars[$m[1][$k]] = $m[2][$k];
                        break;
                }
                $templ = str_replace( $str, "", $templ );
            }
        }
        
        return trim( $templ );
    }
}