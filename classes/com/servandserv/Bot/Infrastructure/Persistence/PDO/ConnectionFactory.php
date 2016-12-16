<?php

namespace com\servandserv\Bot\Infrastructure\Persistence\PDO;

class ConnectionFactory
{
    
    private static $instance;
    private $pdo;

    private function __construct( $url, $user, $pass, $opt = NULL )
    {
        if( !$opt ) {
            $opt = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ];
        }
        try {
            $this->pdo = new \PDO( $this->dns( $url ), $user, $pass, $opt );
        } catch ( \PDOException $e ) {
            die( "Connection error: ".$e->getMessage() );
        }
    }

    public static function getConnect( $url, $user, $pass, $opt = NULL )
    {
        if( NULL == self::$instance ) {
            self::$instance = new self( $url, $user, $pass, $opt );
        }
        return self::$instance->pdo;
    }
    
    // "mysql:host=localhost;dbname=bbbot;charset=utf8"
    private function dns( $url )
    {
        $url = parse_url( $url );
        return $url["scheme"].
            ":host=".$url["host"].
            (isset($url["port"])?":".$url["port"]:"").
            ";dbname=".substr($url["path"],1).
            ";charset=utf8";
    }
    
}