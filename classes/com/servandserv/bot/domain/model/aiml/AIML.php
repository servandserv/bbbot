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
            //trigger_error( print_r( $this->category, true ) );
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
        $templ = $this->parseTemplate( $templ, $selected->getThat() );
        /**
        $templ = $this->filterSrai( $templ, $selected->getThat() );
        // подставляем переменные
        $templ = $this->filterDate( $templ );
        $templ = $this->filterBot( $templ );
        // применяем переменные диалога
        $templ = $this->filterGet( $templ );
        // устанавливаем переменные
        $templ = $this->filterSet( $templ );
        */
        
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
    
    // парсим шаблон подставляя в него значения
    private function parseTemplate( $templ, $that )
    {
        $result = "";
        $xr = new \XMLReader();
        $xmlstr = "<?xml version='1.0' encoding='utf-8'?><abrakadabra>".$templ."</abrakadabra>";
        $xr->XML( $xmlstr );
        while( $xr->read() ) {
            if( $xr->nodeType == \XMLReader::TEXT ) {
                $result .= $xr->readString();
            } elseif( $xr->nodeType == \XMLReader::ELEMENT ) {
                switch( $xr->localName ) {
                    case "srai":
                        $result .= $this->parseSrai( $xr, $that );
                        break;
                    case "get":
                        $result .= $this->parseGet( $xr );
                        break;
                    case "date":
                        $result .= $this->parseDate( $xr );
                        break;
                    case "bot":
                        $result .= $this->parseBot( $xr );
                        break;
                    case "set":
                        $this->parseSet( $xr );
                        break;
                }
            }
        }
        
        return $result;
    }
    
    private function parseSrai( \XMLReader $xr, $that )
    {
        $srai = "";
        while( $xr->read() ) {
            if( $xr->nodeType == \XMLReader::TEXT ) {
                $srai .= $xr->readString();
            } elseif( $xr->nodeType == \XMLReader::END_ELEMENT && $xr->localName == "srai" ) {
                break;
            }
        }
        if( $cat = $this->searchSrai( strtolower( $srai ), $that ) ) {
            $templ = $cat->getTemplate( array_merge( $this->env, $this->vars ) );
            return $this->parseTemplate( $templ, $that );
        } else {
            return "";
        }
    }
    
    private function parseGet( \XMLReader $xr )
    {
        if( isset( $this->vars[$xr->getAttribute("name")] ) ) {
            return $this->vars[$xr->getAttribute("name")];
        } else {
            return "";
        }
    }
    
    private function parseBot( \XMLReader $xr )
    {
        if( isset( $this->env[$xr->getAttribute("name")] ) ) {
            return $this->env[$xr->getAttribute("name")];
        } else {
            return "";
        }
    }
    
    private function parseDate( \XMLReader $xr )
    {
        return date( $xr->getAttribute( "format" ) );
    }
    
    private function parseSet( \XMLReader $xr )
    {
        $var = $xr->getAttribute( "name" );
        $val = "";
        while( $xr->read() ) {
            if( $xr->nodeType == \XMLReader::TEXT ) {
                $val .= $xr->readString();
            } elseif( $xr->nodeType == \XMLReader::ELEMENT ) {
                switch( $xr->localName ) {
                    case "get":
                        $val .= $this->parseGet( $xr );
                        break;
                    case "bot":
                        $val .= $this->parseBot( $xr );
                        break;
                    case "date":
                        $val .= $this->parseDate( $xr );
                        break;
                }
            } elseif( $xr->nodeType == \XMLReader::END_ELEMENT && $xr->localName == "set" ) {
                switch( $var ) {
                    case "command":
                        $this->commands[] = $val;
                        $this->vars["COMMAND"] = $val;
                        break;
                    default:
                        $this->vars[$var] = $val;
                }
                
                return;
            }
        }
    }
    
    private function searchSrai( $pattern, $that = NULL )
    {
        foreach( $this->category as $cat ) {
            if( $cat->getPattern() == $pattern && ( $cat->getThat() == NULL || $cat->getThat() == $that ) ) {
                return $cat;
            }
        }
    }
}