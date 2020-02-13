<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\bot\domain\model\events\Publisher;
use \com\servandserv\data\bot\Update;
use \com\servandserv\data\bot\Command;
use \com\servandserv\data\bot\Commands;

class CommandsFactory {

    private $commands;

    public function __construct(array $commands) {
        $this->commands = $commands;
    }

    public function createCommand(Update $up, Publisher $pubsub) {
        $com = NULL;
        foreach ($this->commands as $className) {
            if (class_exists($className) && call_user_func_array($className . "::fit", [$up])) {
                $cl = new \ReflectionClass($className);
                $com = call_user_func_array(array(&$cl, 'newInstance'), [$pubsub, $this]);
                break;
            }
        }

        return $com;
    }

    public function createDTO() {
        $commands = new Commands();
        ;
        foreach ($this->commands as $clName) {
            if ($clName::$name !== NULL) {
                $command = new Command();
                $command->setComments($clName::$name)->setName($clName::$command);
                $commands->setCommand($command);
            }
        }
        return $commands;
    }

}
