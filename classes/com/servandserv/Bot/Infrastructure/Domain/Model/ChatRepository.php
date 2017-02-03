<?php

namespace com\servandserv\Bot\Infrastructure\Domain\Model;

class ChatRepository 
    extends \com\servandserv\Bot\Infrastructure\Persistence\PDO\Repository 
    implements \com\servandserv\Bot\Domain\Model\ChatRepository
{

    const DESC = "DESC";
    const ASC = "ASC";
    const START = 0;
    const COUNT = 100;

    public function findByKeys( array $keys ) 
    {
        $chat = NULL;
        $params = [
            ":entityId"=>$this->getEntityId( $keys )
        ];
        //$query = "SELECT `ch`.*, `u`.* FROM `nchats` AS `ch`";
        //$query .= " LEFT JOIN `nusers` AS `u` ON `u`.`entityId`=`ch`.`entityId`";
        //$query .= " WHERE `ch`.`entityId`=:entityId;";
        $query = "SELECT * FROM `nchats` WHERE `entityId`=:entityId;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $chat = $this->chatFromRow( $row );
        }
        return $chat;
    }
    
    public function findByPhoneNumber( $phoneNumber )
    {
        $chat = NULL;
        $params = [ ":phoneNumber" => str_replace( "+", "", $phoneNumber ) ];
        $query = "SELECT ch.* FROM `nchats` AS `ch` JOIN `ncontacts` AS `c` ON `ch`.`entityId`=`c`.`entityId` WHERE `c`.`phoneNumber`=:phoneNumber;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $chat = $this->chatFromRow( $row );
        }
        return $chat;
    }
    
    public function findByUID( $uid )
    {
        $chat = NULL;
        $params = [ ":UID" => $UID ];
        $query = "SELECT * FROM `nchats` WHERE `UID`=:UID;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $chat = $this->chatFromRow( $row );
        }
        return $chat;
    }
    
    public function findAll()
    {
        $chats = [];
        $params = [];
        $query = "SELECT * FROM `nchats` ORDER BY `updated` DESC;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $chats[] = $this->chatFromRow( $row );
        }
        return $chats;
    }

    public function locationsFor( \com\servandserv\data\bot\Chat $chat )
    {
        $params = [ ":entityId" => $this->getEntityId( [$chat->getId(), $chat->getContext()] )];
        $query = "SELECT * FROM `nlocations` WHERE `entityId`=:entityId ORDER BY `updated` DESC;";
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
        $query = "SELECT * FROM `ncontacts` WHERE `entityId`=:entityId ORDER BY `updated` DESC;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        while( $row = $sth->fetch() ) {
            $loc = new \com\servandserv\data\bot\Contact();
            $loc->fromMarkupArray( $row );
            $chat->setContact( $loc );
        }
        
        return $chat;
    }
    
    public function commandsFor( \com\servandserv\data\bot\Chat $chat, $order = self::DESC, $start = self::START, $count = self::COUNT )
    {
        $params = [ ":entityId" => $this->getEntityId( [$chat->getId(), $chat->getContext()] )];
        $limit = "";
        if( $count ) $limit = " LIMIT $start,".strval( $start + $count );
        $query = "SELECT * FROM `ncommands` WHERE `entityId`=:entityId ORDER BY `updated` $order $limit;";
        $sth = $this->conn->prepare( $query );
        $sth->execute( $params );
        $coms = [];
        while( $row = $sth->fetch() ) {
            $com = new \com\servandserv\data\bot\Command();
            $com->fromMarkupArray( $row );
            $chat->setCommand( $com );
        }
        
        return $chat;
    }
    
    private function chatFromRow( array $row )
    {
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
            
        return $chat;
    }
}