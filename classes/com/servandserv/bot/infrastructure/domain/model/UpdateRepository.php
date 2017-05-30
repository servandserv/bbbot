<?php

namespace com\servandserv\bot\infrastructure\domain\model;

use \com\servandserv\bot\infrastructure\persistence\pdo\Repository as PDORepository;
use \com\servandserv\bot\domain\model\UpdateRepository as UpdateRepositoryInterface;
use \com\servandserv\data\bot\Update;
use \com\servandserv\data\bot\Command;

class UpdateRepository extends PDORepository implements UpdateRepositoryInterface
{

    const CREATED = 1;
    const EXECUTED = 2;
    const POSTPONED = 3;

    public function register( Update $up )
    {
        $entityId = $this->getEntityIdFromUpdate( $up );
        $chat = $up->getChat();
        $params = [
            ":entityId" => $entityId,
            ":id" => $chat->getId(),
            ":type" => $chat->getType(),
            ":context" => $chat->getContext()
        ];
        $msg = $up->getMessage();
        if( $msg && $msg->getUser() ) {
            $params[":firstName"] = $msg->getUser()->getFirstName();
            $params[":lastName"] = $msg->getUser()->getLastName();
            
            $user = [
                ":entityId" => $entityId,
                ":firstName" => $msg->getUser()->getFirstName(),
                ":lastName" => $msg->getUser()->getLastName(),
                ":middleName" => $msg->getUser()->getMiddleName(),
                ":gender" => $msg->getUser()->getGender(),
                ":locale" => $msg->getUser()->getLocale()
            ];
            
            $query = "";
            foreach( $user as $col => $val ) {
                $query .= ",`".substr( $col, 1 )."`=".$col;
            }
            $query = "INSERT INTO `nusers` SET ".substr( $query, 1 )." ON DUPLICATE KEY UPDATE ".substr( $query, 1 );
            $sth = $this->conn->prepare( $query );
            $sth->execute( $user );
        }
        if( $msg && $msg->getLocation() ) {
            $params[":latitude"] = $msg->getLocation()->getLatitude();
            $params[":longitude"] = $msg->getLocation()->getLongitude();
            
            $location = [
                ":entityId" => $entityId,
                ":latitude" => $msg->getLocation()->getLatitude(),
                ":longitude" => $msg->getLocation()->getLongitude()
            ];
            $query = "";
            foreach( $location as $col => $val ) {
                $query .= ",`".substr( $col, 1 )."`=".$col;
            }
            $query = "INSERT INTO `nlocations` SET ".substr( $query, 1 ).";";
            $sth = $this->conn->prepare( $query );
            $sth->execute( $location );
        }
        // check if contact id equal to chat id !!!!!!
        // user can send any contact from his contact book
        // @todo grab and create new user if contact user id is different
        if( $msg && $msg->getContact() && $msg->getContact()->getUser()->getId() == $chat->getId() ) {
        
            $num = $msg->getContact()->getPhoneNumber();
            $params[":phoneNumber"] = $num;
            
            $contact = [
                ":entityId" => $entityId,
                ":phoneNumber" => $num
            ];
            $query = "";
            foreach( $contact as $col => $val ) {
                $query .= ",`".substr( $col, 1 )."`=".$col;
            }
            $query = "INSERT INTO `ncontacts` SET ".substr( $query, 1 )." ON DUPLICATE KEY UPDATE ".substr( $query, 1 ).";";
            $sth = $this->conn->prepare( $query );
            $sth->execute( $contact );
        }
        $query = "";
        foreach( $params as $col => $val ) {
            $query .= ",`".substr( $col, 1 )."`=".$col;
        }
        $query = "INSERT INTO `nchats` SET ".substr( $query, 1 )." ON DUPLICATE KEY UPDATE ".substr( $query, 1 );
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        
        /**
        $command = $up->getCommand();
        if( $command ) {
            $params = [
                ":entityId"=>$entityId,
                ":command"=>$command->getName(),
                ":alias"=>$command->getAlias(),
                ":arguments"=>$command->getArguments()
            ];
            $query = "";
            foreach( $params as $col => $val ) {
                $query .= ",`".substr( $col, 1 )."`=".$col;
            }
            $query = "INSERT INTO `ncommands` SET ".substr( $query, 1 ).";";
            $sth = $this->conn->prepare( $query );
            $sth->execute( $params );
        }
        */
        
        $params = [
            ":entityId" => $entityId,
            ":context" => $chat->getContext(),
            ":update" => $up->toXmlStr(),
            ":status" => self::CREATED
        ];
        $query = "";
        foreach( $params as $col => $val ) {
            $query .= ",`".substr( $col, 1 )."`=".$col;
        }
        $query = "INSERT INTO `nupdates` SET ".substr( $query, 1 ).";";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        
        return $entityId;
    }
    
    public function archive( $autoid, Update $up )
    {
        $params = [ 
            "autoid" => $autoid,
            "status" => $up->getStatus(),
            "xmlstr" => $up->toXmlStr()
        ];
        $query = "UPDATE `nupdates` SET `status`=:status, `update`=:xmlstr WHERE `autoid`=:autoid;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
    }
    
    public function registerCommand( Update $up )
    {
        $entityId = $this->getEntityIdFromUpdate( $up );
        $params = [
            ":entityId"=>$entityId,
            ":command"=>$up->getCommand()->getName(),
            ":alias"=>$up->getCommand()->getAlias(),
            ":arguments"=>$up->getCommand()->getArguments()
        ];
        $query = "";
        foreach( $params as $col => $val ) {
            $query .= ",`".substr( $col, 1 )."`=".$col;
        }
        $query = "INSERT INTO `ncommands` SET ".substr( $query, 1 ).";";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
    }
    
    public function findAllActive()
    {
        $updates = [];
        $params = [ "status" => self::CREATED ];
        $query = "SELECT `autoid`, `update` FROM `nupdates` WHERE `status`=:status;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $up = ( new Update() )->fromXmlStr( $row["update"] );
            $updates[$row["autoid"]] = $up;
        }
        
        return $updates;
    }
}