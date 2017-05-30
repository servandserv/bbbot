<?php

namespace com\servandserv\bot\infrastructure\domain\model;

use \com\servandserv\bot\domain\model\AIMLRepository;
use \com\servandserv\bot\domain\model\aiml\AIML;
use \com\servandserv\bot\domain\model\aiml\Category;
use \com\servandserv\bot\domain\model\aiml\Template;
use \com\servandserv\bot\domain\model\aiml\Random;
use \com\servandserv\bot\domain\model\aiml\Condition;

class AIMLFileRepository implements AIMLRepository
{

    const ROOT = "aiml";
    const NS = "http://alicebot.org/2001/AIML-1.0.1";

    private static $aiml;
    
    public function __construct( $href )
    {
        $this->href = $href;
    }
    
    public function read()
    {
        if( NULL === self::$aiml ) {
            try {
                $xr = new \XMLReader();
                if( $str = file_get_contents( $this->href ) ) {
                    if( $xr->XML( $str ) ) {
                        while ( $xr->nodeType != \XMLReader::ELEMENT ) $xr->read();
                        self::$aiml = $this->aimlFromXmlReader( $xr );
                    } else trigger_error( "File ".$this->href." XMLReader reading error in file ".__FILE__." line ".__LINE__ );
                } else trigger_error( "File ".$this->href." reading error in file ".__FILE__." line ".__LINE__ );
            } catch( \Exception $e ) {
                trigger_error( $e->getMessage()." in file ".$e->getFile()." on line ".$e->getLine().":".$e->getTraceAsString() );
            }
        }
        return self::$aiml;
    }
    
    private function aimlFromXmlReader( $xr )
    {
        $aiml = new AIML();
        $topic = "unknown";
		while ( $xr->read() ) {
			if ( $xr->nodeType == \XMLReader::ELEMENT && $xr->namespaceURI == self::NS ) {
			    switch( $xr->localName ) {
			        case "topic":
			            $topic = $xr->getAttribute("name");
			            break;
			        case "category":
			            $category = $this->categoryFromXmlReader( $xr );
			            $category->setTopic( $topic );
			            $aiml->setCategory( $category );
			            break;
			    }
			} elseif ( $xr->nodeType == \XMLReader::END_ELEMENT && self::ROOT == $xr->localName ) {
				return $aiml;
			}
		}
		return $aiml;
    }
    
    private function categoryFromXmlReader( $xr )
    {
        $category = new Category();
        while ( $xr->read() ) {
			if ( $xr->nodeType == \XMLReader::ELEMENT && $xr->namespaceURI == self::NS ) {
			    switch( $xr->localName ) {
			        case "pattern":
			            $category->setPattern( $xr->readString() );
			            break;
			        case "template":
			            $template = $this->templateFromXmlReader( $xr );
			            $category->setTemplate( $template );
			            break;
			        case "that":
			            $category->setThat( $xr->readInnerXML() );
			            break;
			    }
			} elseif ( $xr->nodeType == \XMLReader::END_ELEMENT && "category" == $xr->localName ) {
				return $category;
			}
		}
        return $category;
    }
    
    private function templateFromXmlReader( $xr )
    {
        $template = ( new Template() )->setText( $xr->readInnerXML() );
        while ( $xr->read() ) {
			if ( $xr->nodeType == \XMLReader::ELEMENT && $xr->namespaceURI == self::NS ) {
			    switch( $xr->localName ) {
			        case "random":
			            $random = $this->randomFromXmlReader( $xr );
			            $template->setRandom( $random );
			            break;
			        case "condition":
			            $condition = $this->conditionFromXmlReader( $xr );
			            $template->setCondition( $condition );
			            break;
			    }
			} elseif ( $xr->nodeType == \XMLReader::END_ELEMENT && "template" == $xr->localName ) {
				return $template;
			}
		}
        return $template;
    }
    
    private function randomFromXmlReader( $xr )
    {
        $random = new Random();
        while ( $xr->read() ) {
			if ( $xr->nodeType == \XMLReader::ELEMENT && $xr->namespaceURI == self::NS ) {
			    switch( $xr->localName ) {
			        case "li":
			            $random->setLi( $xr->readInnerXML() );
			            break;
			    }
			} elseif ( $xr->nodeType == \XMLReader::END_ELEMENT && "random" == $xr->localName ) {
				return $random;
			}
		}
        return $random;
    }
    
    private function conditionFromXmlReader( $xr )
    {
        $condition = ( new Condition() )->setName( $xr->getAttribute( "name" ) );
        while ( $xr->read() ) {
			if ( $xr->nodeType == \XMLReader::ELEMENT && $xr->namespaceURI == self::NS ) {
			    switch( $xr->localName ) {
			        case "li":
			            $value = $xr->getAttribute( "value" ) ? $xr->getAttribute( "value" ) : NULL;
			            $pattern = $xr->getAttribute( "pattern" ) ? $xr->getAttribute( "pattern" ) : NULL;
			            $condition->setLi( $xr->readInnerXML(), $value, $pattern );
			            break;
			    }
			} elseif ( $xr->nodeType == \XMLReader::END_ELEMENT && "condition" == $xr->localName ) {
				return $condition;
			}
		}
        return $condition;
    }
}