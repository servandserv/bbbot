<?php

namespace com\servandserv\Bot\Infrastructure\Persistence\PDO;

class ConnectionFactory
{
    
    private static $instance;
    private $pdo;

    private function __construct( $dns, $user, $pass, $opt )
    {
        $opt = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ];
        try {
            $this->pdo = new \PDO( $dns, $user, $pass, $opt );
        } catch ( \PDOException $e ) {
            die( "Connection error: ".$e->getMessage() );
        }
    }

    public static function getConnect( $dns, $user, $pass, $opt )
    {
        if( NULL == self::$instance ) {
            self::$instance = new self( $dns, $user, $pass, $opt );
        }
        return self::$instance->pdo;
    }
    
}