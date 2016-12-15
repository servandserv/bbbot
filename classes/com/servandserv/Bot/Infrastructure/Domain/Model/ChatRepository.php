<?php

namespace com\servandserv\Bot\Infrastructure\Domain\Model;

class ChatRepository 
    extends \com\servandserv\Bot\Infrastructure\Persistence\PDO\Repository 
    implements \com\servandserv\Bot\Domain\Model\ChatRepository
{

    public function findByKeys( array $keys ) 
    {
        $chat = NULL;
        $params = [
            ":entityId"=>$this->getEntityId( $keys )
        ];
        $query = "SELECT `ch`.*, `u`.* FROM `nchats` AS `ch`";
        $query .= " LEFT JOIN `nusers` AS `u` ON `u`.`entityId`=`ch`.`entityId`";
        $query .= " WHERE `ch`.`entityId`=:entityId;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $chat = new \com\servandserv\data\bot\Chat();
            $chat->fromMarkupArray( $row );
            $user = new \com\servandserv\data\bot\User();
            $chat->setUser( $user->fromMarkupArray( $row ) );
            if( $row["latitude"] && $row["longitude"] ) {
                $loc = new \com\servandserv\data\bot\Location();
                $chat->setLocation( $loc->fromMarkupArray( $row ) );
            }
            if( $row["phoneNumber"] ) {
                $c = new \com\servandserv\data\bot\Contact();
                $chat->setContact( $c->fromMarkupArray( $row ) );
            }
        }
        return $chat;
    }
    
    public function findByPhoneNumber( $phoneNumber )
    {
        $chat = NULL;
        $params = [ ":phoneNumber" => $phoneNumber ];
        $query = "SELECT ch.* FROM `nchats` AS `ch` JOIN `ncontacts` AS `c` ON `ch`.`entityId`=`c`.`entityId` WHERE `c`.`phoneNumber`=:phoneNumber;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $chat = new \com\servandserv\data\bot\Chat();
            $chat->fromMarkupArray( $row );
            $user = new \com\servandserv\data\bot\User();
            $chat->setUser( $user->fromMarkupArray( $row ) );
            if( $row["latitude"] && $row["longitude"] ) {
                $loc = new \com\servandserv\data\bot\Location();
                $chat->setLocation( $loc->fromMarkupArray( $row ) );
            }
            if( $row["phoneNumber"] ) {
                $c = new \com\servandserv\data\bot\Contact();
                $chat->setContact( $c->fromMarkupArray( $row ) );
            }
        }
        return $chat;
    }

    public function locationsFor( \com\servandserv\data\bot\Chat $chat )
    {
        $params = [ ":entityId" => $this->getEntityId( [$chat->getId(), $chat->getContext()] )];
        $query = "SELECT * FROM `nlocations` WHERE `entityId`=:entityId;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $loc = new \com\servandserv\data\bot\Location();
            $loc->fromMarkupArray( $row );
            $chat->setLocation( $loc );
        }
        
        return $chat;
    }
    
    public function contactsFor( \com\servandserv\data\bot\Chat $chat )
    {
        $params = [ ":entityId" => $this->getEntityId( [$chat->getId(), $chat->getContext()] )];
        $query = "SELECT * FROM `ncontacts` WHERE `entityId`=:entityId;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $loc = new \com\servandserv\data\bot\Contact();
            $loc->fromMarkupArray( $row );
            $chat->setContact( $loc );
        }
        
        return $chat;
    }
    
    public function commandsFor( \com\servandserv\data\bot\Chat $chat )
    {
        $params = [ ":entityId" => $this->getEntityId( [$chat->getId(), $chat->getContext()] )];
        $query = "SELECT * FROM `ncommands` WHERE `entityId`=:entityId;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $loc = new \com\servandserv\data\bot\Command();
            $loc->fromMarkupArray( $row );
            $chat->setCommand( $loc );
        }
        
        return $chat;
    }
}