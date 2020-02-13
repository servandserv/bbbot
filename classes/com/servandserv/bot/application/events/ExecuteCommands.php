<?php

namespace com\servandserv\bot\application\events;

//use \com\servandserv\happymeal\errors\Error;
//use \com\servandserv\happymeal\errors\Errors;
//use \com\servandserv\data\bot\Update;
//use \com\servandserv\data\bot\Chat;
//use \com\servandserv\data\bot\Commands;
//use \com\servandserv\data\bot\Command;
//use \com\servandserv\data\bot\Message;

use \com\servandserv\bot\application\ServiceLocator;
use \com\servandserv\bot\domain\model\CommandsFactory;
use \com\servandserv\bot\domain\model\events\Subscriber;
use \com\servandserv\bot\domain\model\events\Publisher;
use \com\servandserv\bot\domain\model\events\Event;
use \com\servandserv\bot\domain\model\events\UserNotFoundOccuredEvent;
use \com\servandserv\bot\domain\model\UserNotFoundException;
use \com\servandserv\bot\domain\model\events\ExceptionOccuredEvent;
use \com\servandserv\bot\infrastructure\AsyncLazy;

//use \com\servandserv\bot\domain\model\Dialog;

class ExecuteCommands extends AsyncLazy implements Subscriber {

    protected $commsFactory;

    public function __construct($id = NULL, CommandsFactory $commsFactory = NULL) {
        if ($commsFactory) {
            $this->commsFactory = $commsFactory;
        }
        parent::__construct($id);
    }

    public function isSubscribedTo(Event $event) {
        // реагируем на событие регистрации апдейтов
        return ( $event instanceof \com\servandserv\bot\domain\model\events\UpdatesRegisteredEvent );
    }

    public function handle(Event $event) {
        /**
          $params["event"] = $event;
          $params["commands"] = $this->commands;
          $this->setBootstrap( "../conf/conf.php" );
          $this->fork( $params );
         */
        /**
         *
         * Запускаем один раз и работаем пока не выполнили всю работу
         * для этого создаем блокировку которую проверяем при следующем заходе
         * если работа еще не закончена, то блокировка  снята, проходим мимо
         * если блокировки нет снова запускаем процесс
         *
         */
        $unique = $event->getContext();
        //$unique = md5(microtime(true));
        $lockfile = sys_get_temp_dir() . "/" . str_replace("/", "_", __FILE__) . "_" . $unique . ".lock";
        //if ( file_exists( $lockfile ) ) {
        //    sleep( 5 );
        //}
        $lockfp = fopen($lockfile, "w");
        if (!flock($lockfp, LOCK_EX | LOCK_NB)) {
            // работа все еще выполняется
            fclose($lockfp);
        } else {
            // похоже никакой активной работы нет пытаемся запустить ее выполнение
            $params["lockfile"] = $lockfile;
            $params["event"] = $event;
            $params["commsFactory"] = $this->commsFactory;
            $this->setBootstrap("../conf/conf.php");
            $this->fork($params);

            flock($lockfp, LOCK_UN);
            fclose($lockfp);
        }
    }

    public function run($args) {
        try {

            $lockfile = $args["lockfile"];
            $lockfp = fopen($lockfile, "w");
            $start = time();
            //ждем пока нам освободит блокировку вызывавший скрипт (10 сек)
            while (!flock($lockfp, LOCK_EX | LOCK_NB) && time() - $start < 10) {

            }

            //пытаемся заблокировать сами
            if (!flock($lockfp, LOCK_EX | LOCK_NB)) {
                fclose($lockfp);
                // можем упасть, следующий апдейт снова активирует скрипт
                trigger_error("lock file $lockfile error in " . __FILE__ . " on " . __LINE__);
            }

            $sl = \Locator::getInstance();
            $pubsub = $args["event"]->getPubSub();
            $context = $args["event"]->getContext();
            $updates = $args["event"]->getUpdates()->getUpdate();
            $commsFactory = $args["commsFactory"];

            /**
             * Читаем все новые апдейты
             * прогоняем их через команды
             * после прогона архивируем
             * повторяемся пока не прочитаем все в очереди
             */
            $rep = $sl->create("com.servandserv.bot.domain.model.UpdateRepository");
            $updates = $rep->findAllActive($context);

            while (!empty($updates)) {
                foreach ($updates as $autoid => $update) {
                    try {
                        $com = $commsFactory->createCommand($update, $pubsub);
                        if ($com != NULL) {
                            if ($update->getCommand()) {
                                $rep->registerCommand($update);
                            }
                            $port = $sl->create("com.servandserv.bot.domain.model.BotPort", [$update->getContext(), $pubsub]);
                            $com->execute($update, $sl, $port);
                        }
                        // фиксируем, что обработан
                        $update->setStatus($rep::EXECUTED);
                    } catch (UserNotFoundException $e) {
                        // не нашли пользователя
                        // надо сообщить об этом
                        $update->setStatus($rep::POSTPONED);
                        $pubsub->publish(new UserNotFoundOccuredEvent($update->getChat()));
                    } catch (\Exception $e) {
                        // случилась не понятная нам ошибка
                        // уведомим админа
                        $update->setStatus($rep::POSTPONED);
                        $pubsub->publish(new ExceptionOccuredEvent(new \Exception("Error on update \"$autoid\" execute command\n" . $update->getRaw(), 0, $e)));
                    }
                    // поместим в архив
                    $rep->archive($autoid, $update);
                }
                // посмотрим может еще кто-то прилетел
                $updates = $rep->findAllActive($context);
            }

            // и закроем асинхронную часть
            $this->setResult(TRUE);
            $this->close();
        } catch (\Exception $e) {
            // что-то не задалось
            // закроем асинхронную часть
            // глобальную блокировку говорят отпустит само
            $this->setResult(TRUE);
            $this->close();
            $pubsub->publish(new ExceptionOccuredEvent($e));
            //trigger_error( $e->getMessage()." in file ".$e->getFile()." on line ".$e->getLine() );
        }
    }

}
