<?php

namespace com\servandserv\Bot\Infrastructure\Domain\Model;

class RequestRepository 
    extends \com\servandserv\Bot\Infrastructure\Persistence\PDO\Repository 
    implements \com\servandserv\Bot\Domain\Model\RequestRepository
{
    public function register( \com\servandserv\data\bot\Request $req )
    {
        $params = [
            ":entityId" => $req->getEntityId(),
            ":id" => $req->getId(),
            ":outerId" => $req->getOuterId(),
            ":json" => $req->getJson(),
            ":watermark" => $req->getWatermark()
        ];
        $query = "";
        foreach( $params as $col => $val ) {
            $query .= ",`".substr($col, 1)."`=".$col;
        }
        $query = "INSERT INTO `nrequests` SET ".substr( $query, 1 )." ON DUPLICATE KEY UPDATE ".substr( $query, 1 );
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
    }
    
    public function delivery( \com\servandserv\data\bot\Chat $chat, \com\servandserv\data\bot\Delivery $del )
    {
        $mids = $del->getMid();
        if( is_array( $mids ) && count( $mids ) > 0 ) {
            foreach( $mids as $mid ) {
                $params = [ ":id" => $mid, ":delivered" => $del->getWatermark()  ];
                $query = "UPDATE `nrequests` SET `delivered`=:delivered WHERE `id`=:id;";
                $sth = $this->conn->prepare( $query );
                $sth->execute( $params );
            }
        }
        $entityId = $this->getEntityIdFromChat( $chat );
        $params = [ ":entityId" => $entityId, ":delivered" => $del->getWatermark(), ":watermark" => $del->getWatermark() ];
        $query = "UPDATE `nrequests` SET `delivered`=:delivered WHERE `entityId`=:entityId AND `watermark` < :watermark AND `delivered` IS NULL;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
    }
    
    public function read( \com\servandserv\data\bot\Chat $chat, \com\servandserv\data\bot\Read $read )
    {
        $mids = $read->getMid();
        if( is_array( $mids ) && count( $mids ) > 0 ) {
            foreach( $mids as $mid ) {
                $params = [ ":id" => $mid, ":read" => $read->getWatermark()  ];
                $query = "UPDATE `nrequests` SET `read`=:read WHERE `id`=:id;";
                $sth = $this->conn->prepare( $query );
                $sth->execute( $params );
            }
        }
        $entityId = $this->getEntityIdFromChat( $chat );
        $params = [ ":entityId" => $entityId, ":read" => $read->getWatermark(), ":watermark" => $read->getWatermark() ];
        $query = "UPDATE `nrequests` SET `read`=:read WHERE `entityId`=:entityId AND `watermark` < :watermark AND `read` IS NULL;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
    }
    
    
    public function findByStatus( $status )
    {
        $params = [ ":status" => $status ];
        $query = "SELECT * FROM `nrequests` WHERE `status`=:status;";
        
    }
}