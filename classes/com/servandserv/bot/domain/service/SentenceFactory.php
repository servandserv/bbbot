<?php

namespace com\servandserv\bot\domain\service;

use \com\servandserv\data\bot\Dict;
use \com\servandserv\data\bot\Word;
use \com\servandserv\data\bot\Sentence;

class SentenceFactory
{

    const MINLEN = 3;

    private $dict;
    private $fm;

    public function __construct( FuzzyMean $fm, Dict $dict )
    {
        $this->dict = $dict;
        $this->fm = $fm;
    }
    
    public function getDict()
    {
        return $this->dict;
    }
    
    public function parse( $in, $minlen = self::MINLEN )
    {
        $res = [];
        $words = explode( " ", $in );
        foreach( $words as $word ) {
            if( strlen( $word ) >  $minlen ) {
                $res[] = $word;
            }
        }
        
        return $res;
    }
    
    public function create( $in )
    {
        $s = new Sentence();
        $words = $this->parse( $in );
        $verbs = $this->dict->getWord( NULL, function( $w ) {
            return $w->getPos() === "verb";
        });
        $s->setVerb( $this->search( $words, $verbs ) );
        $nouns = $this->dict->getWord( NULL, function( $w ) {
            return $w->getPos() === "noun";
        });
        $s->setNoun( $this->search( $words, $nouns )  );
        
        return $s;
    }
    
    private function search( array &$words, array $dwords )
    {
        $res = NULL;
        foreach( $words as $word ) {
            $res = $this->fm->correct( $dwords, $word );
            if( $res ) {
                $words = array_filter( $words, function( $item ) use ( $word ) {
                    if( $item != $word ) return TRUE;
                });
                break;
            }
        }
        
        return $res;
    }
}