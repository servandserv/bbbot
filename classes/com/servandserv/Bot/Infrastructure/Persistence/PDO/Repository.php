<?php

namespace com\servandserv\Bot\Infrastructure\Persistence\PDO;

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
        $this->conn->commit();
    }
    
    public function rollback()
    {
        $this->conn->rollback();
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
    
    public function getEntityIdFromChat( \com\servandserv\data\bot\Chat $chat )
    {
        return $this->getEntityId( [ $chat->getId(), $chat->getContext() ] );
    }
    
    public function getEntityIdFromUpdate( \com\servandserv\data\bot\Update $up )
    {
        return $this->getEntityIdFromChat( $up->getChat() );
    }
}