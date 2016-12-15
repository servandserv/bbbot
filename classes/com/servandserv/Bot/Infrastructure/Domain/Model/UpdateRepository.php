<?php

namespace com\servandserv\Bot\Infrastructure\Domain\Model;

class UpdateRepository 
    extends \com\servandserv\Bot\Infrastructure\Persistence\PDO\Repository
    implements \com\servandserv\Bot\Domain\Model\UpdateRepository
{

    public function register( \com\servandserv\data\bot\Update $up )
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
        if( $msg && $msg->getContact() ) {
            $params[":phoneNumber"] = $msg->getContact()->getPhoneNumber();
            
            $contact = [
                ":entityId" => $entityId,
                ":phoneNumber" => $msg->getContact()->getPhoneNumber()
            ];
            $query = "";
            foreach( $contact as $col => $val ) {
                $query .= ",`".substr( $col, 1 )."`=".$col;
            }
            $query = "INSERT INTO `ncontacts` SET ".substr( $query, 1 ).";";
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
        
        $params = [
            ":entityId" => $entityId,
            ":context" => $chat->getContext(),
            ":update" => $up->toXmlStr(),
            ":status" => 1
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
    
}