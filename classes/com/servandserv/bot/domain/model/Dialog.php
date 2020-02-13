<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\bot\domain\model\aiml\AIML;
use \com\servandserv\bot\domain\service\FuzzyMean;
use \com\servandserv\data\bot\Dialog as DialogTDO;
use \com\servandserv\data\bot\Interchange;
use \com\servandserv\data\bot\Variable;

class Dialog extends DialogTDO {

    public function getLastAnswer() {
        $history = $this->getInterchange();
        $lastanswer = NULL;
        if (!empty($history)) {
            $lastanswer = end($history)->getAnswer();
        }

        return $lastanswer;
    }

    public function setLastAnswer($answer) {
        $history = $this->getInterchange();
        if (!empty($history)) {
            $interchange = array_pop($history);
            $interchange->setAnswer($answer);
            $history[] = $interchange;
            $this->setInterchangeArray($history);
        }

        return $this;
    }

    public function getCurrentTopic() {
        $topic = $this->getVariable(NULL, function( $v ) {
            return $v->getName() == "topic";
        });
        if (!empty($topic))
            return $topic[0]->getValue();
    }

    public function setCurrentTopic($topic) {
        $this->updateVariable(( new Variable())->setName("topic")->setValue($topic));
        return $this;
    }

    public function varsToAssocArray() {
        $vars = [];
        foreach ($this->getVariable() as $var) {
            $vars[$var->getName()] = $var->getValue();
        }

        return $vars;
    }

    public function varsFromAssocArray(array $vars) {
        $this->setVariableArray([]); //обнулим потому что некоторые переменные могут быть переопределены в ответах клиента
        foreach ($vars as $k => $v) {
            $this->setVariable(( new Variable())->setName($k)->setValue($v));
        }

        return $this;
    }

    public function createInterchange($question, $answer) {
        return ( new Interchange())
                        ->setCreated(intval(microtime(true) * 1000))
                        ->setQuestion($question)
                        ->setAnswer($answer);
    }

    /**
     * обновляем содержимое переменной
     * если переменной небыло, то создаем ее
     * если значение переменно равно NULL, то просто не создаем ее
     */
    public function updateVariable(Variable $newvar) {
        $vars = $this->getVariable();
        if ($newvar->getValue() === NULL) {
            $newvars = [];
        } else {
            $newvars = [$newvar->getName() => $newvar];
        }
        foreach ($vars as $var) {
            if ($var->getName() == $newvar->getName()) {
                continue;
            }
            $newvars[$newvar->getName()] = $var;
        }
        $this->setVariableArray($newvars);

        return $this;
    }

    public function removeVariables(array $names) {
        $vars = $this->getVariable();
        $newvars = [];
        foreach ($vars as $var) {
            if (!in_array($var->getName(), $names)) {
                $newvars[$var->getName()] = $var;
            }
        }
        $this->setVariableArray($newvars);

        return $this;
    }

    public function getVariableByName($name) {
        $vars = $this->getVariable();
        foreach ($vars as $var) {
            if ($var->getName() == $name)
                return $var;
        }

        return NULL;
    }

    public function getLog() {
        $log = "";
        $ichs = $this->getInterchange();
        foreach ($ichs as $ich) {
            $log .= "Q: " . $ich->getQuestion() . "\n";
            $log .= "A: " . $ich->getAnswer() . "\n";
        }

        return $log;
    }

}
