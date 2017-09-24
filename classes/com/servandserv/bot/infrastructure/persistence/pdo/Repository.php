<?php

namespace com\servandserv\bot\infrastructure\persistence\PDO;

use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\Update;

class Repository
{
    protected $conn;
    
    public function __construct( $conn )
    {
        $this->conn = $conn;
    }
    
    public function beginTransaction()
    {
        $this->conn->beginTransaction();
    }
    
    public function commit()
    {
        try { $this->conn->commit(); } catch (\Exception $e) {}
    }
    
    public function rollback()
    {
        try { $this->conn->rollBack(); } catch (\Exception $e) {}
    }
    
    public function getEntityId( array $keys ) 
    {
        $strkeys = [];
        foreach( $keys as $key ) {
            $strkeys[] = strval( $key );
        }
        sort( $strkeys );
        return md5( implode( ".", $strkeys ) );
    }
    
    public function getEntityIdFromChat( Chat $chat )
    {
        return $this->getEntityId( [ $chat->getId(), $chat->getContext() ] );
    }
    
    public function getEntityIdFromUpdate( Update $up )
    {
        return $this->getEntityIdFromChat( $up->getChat() );
    }
}