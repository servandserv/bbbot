<?php

namespace com\servandserv\bot\infrastructure\domain\model\telegram;

use \com\servandserv\bot\domain\model\BotPort;
use \com\servandserv\bot\domain\model\RequestRepository;
use \com\servandserv\bot\domain\model\UserNotFoundException;
use \com\servandserv\bot\domain\model\CurlClient;
use \com\servandserv\bot\domain\model\CurlException;
use \com\servandserv\bot\domain\service\Synchronizer;
use \com\servandserv\bot\domain\model\View;
use \com\servandserv\happymeal\XML\Schema\AnyType;
use \org\telegram\data\bot\Update as TelegramUpdate;
use \org\telegram\data\bot\CallbackQueryType;
use \org\telegram\data\bot\UserType;
use \org\telegram\data\bot\MessageType;
use \org\telegram\data\bot\LocationType;
use \org\telegram\data\bot\ContactType;
use \org\telegram\data\bot\ChatType;
use \com\servandserv\data\bot\Request;
use \com\servandserv\data\bot\Updates;
use \com\servandserv\data\bot\Update;
use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\User;
use \com\servandserv\data\bot\Message;
use \com\servandserv\data\bot\Location;
use \com\servandserv\data\bot\Contact;
use \com\servandserv\data\bot\Link;
use \com\servandserv\data\bot\Command;
use \com\servandserv\data\bot\UpdateEventType;

class BotAdapter implements BotPort {

    const CONTEXT = "org.telegram";

    protected $cli;
    protected $NS;
    protected $token;
    protected $rep;
    protected $syn;
    protected $messagesPerSecond;
    protected static $updates;

    public function __construct(CurlClient $cli, $token, $NS, RequestRepository $rep, Synchronizer $syn) {
        $this->cli = $cli;
        $this->token = $token;
        $this->NS = $NS;
        $this->rep = $rep;
        $this->syn = $syn;
    }

    public function makeRequest($name, array $args, callable $cb = NULL) {
        $clName = $this->NS . "\\" . $name;
        if (!class_exists($clName))
            throw new \Exception("Class for VIEW name \"$name\" not exists.");
        $cl = new \ReflectionClass($clName);
        $view = call_user_func_array(array(&$cl, 'newInstance'), $args);
        $this->publishView($view, $cb);
    }

    public function publishView(View $view, callable $cb = NULL) {
        $view->setToken($this->token);
        $requests = $view->getRequests();
        try {
            foreach ($requests as $request) {
                // если идентичный запрос уже отправляли, то пропускаем
                if ($this->rep->findBySignature($request->getSignature()))
                    continue;

                if ($view->isSynchronous()) {
                    $this->syn->next(self::CONTEXT); // следующая отправка
                }
                $watermark = intval(microtime(true) * 1000);
                $resp = $this->cli->request($request);
                $json = json_decode($resp->getBody(), TRUE);
                if ($json && array_key_exists("result", $json) && array_key_exists("message_id", $json["result"])) {
                    $ret = ( new Request())
                            ->setId($json["result"]["message_id"])
                            ->setSignature($request->getSignature())
                            ->setJson($request->getContent())
                            ->setWatermark($watermark);
                    if ($cb) {
                        $cb($ret);
                    }
                }
            }
            //trigger_error(print_r(json_decode($request->getContent()),true));
        } catch (CurlException $e) {
            $str = isset($request) ? $request->getContent() : "";
            switch ($e->getCode()) {
                case "400":
                    if (strstr($e->getMessage(), "chat not found")) {
                        throw new UserNotFoundException($e->getMessage() . ":" . $str, $e->getCode());
                    } else {
                        throw new \Exception($e->getMessage() . ":" . $str, $e->getCode(), $e);
                    }
                    break;
                case "401":
                case "403":
                    throw new UserNotFoundException($e->getMessage() . ":" . $str, $e->getCode());
                    break;
                default:
                    throw new \Exception($e->getMessage() . ":" . $str, $e->getCode(), $e);
            }
        }
    }

    public function getUpdates() {
        if (NULL == self::$updates) {
            self::$updates = ( new Updates())->setContext(self::CONTEXT);
            $in = file_get_contents("php://input");
            if (!$json = json_decode($in, TRUE))
                throw new \Exception("Error on decode update json in " . __FILE__ . " on line " . __LINE__ . "\n input $in");
            $up = $this->translateToUpdate(( new TelegramUpdate())->fromJSONArray($json));
            $up->setRaw($in);
            $up->setIP($this->getIP());
            self::$updates->setUpdate($up);
        }

        return self::$updates;
    }

    public function translateToUpdate(TelegramUpdate $tup) {
        $up = ( new Update())->setId($tup->getUpdate_id())->setContext(self::CONTEXT);
        $up->setChat(( new Chat())->setContext(self::CONTEXT));
        $up->setEvent(UpdateEventType::_RECEIVED);
        $this->fromCallbackQueryType($tup->getCallback_query(), $up);
        // various types of message
        $msg = NULL;
        if ($tup->getMessage()) {
            $msg = $tup->getMessage();
        } elseif ($tup->getEdited_message()) {
            $msg = $tup->getEdited_message();
        } elseif ($tup->getChannel_post()) {
            $msg = $tup->getChannel_post();
        } elseif ($tup->getEdited_channel_post()) {
            $msg = $tup->getEdited_channel_post();
        }
        if ($msg) {
            $this->fromMessageType($msg, $up);
        }

        return $up;
    }

    private function fromCallbackQueryType(CallbackQueryType $cbq = NULL, Update $up) {
        if (!$cbq)
            return NULL;
        $com = ( new Command())
                ->setId($cbq->getId())
                ->setName($cbq->getData());
        $this->fromUserType($cbq->getFrom(), $up->getChat());
        $up->setCommand($com);
        $up->setEvent(UpdateEventType::_POSTBACK);
    }

    private function fromUserType(UserType $from = NULL, Chat $chat) {
        if (!$from)
            return NULL;
        $u = ( new User())
                ->setId($from->getId())
                ->setFirstName($from->getFirst_name())
                ->setLastName($from->getLast_name())
                ->setNickname($from->getUsername());
        $chat->setId($from->getId());
        $chat->setUser($u);
    }

    private function fromMessageType(MessageType $mt = NULL, Update $up) {
        if (!$mt)
            return NULL;
        $m = ( new Message())
                ->setId($mt->getMessage_id())
                ->setDt(str_pad($mt->getDate(), 13, "0"))
                ->setText($mt->getText());
        $this->fromLocationType($mt->getLocation(), $m, $up->getChat());
        $this->fromContactType($mt->getContact(), $m, $up->getChat());
        $this->fromUserType($mt->getFrom(), $up->getChat());
        $this->fromEntities($mt->getEntities(), $m, $up);
        $this->fromChatType($mt->getChat(), $up->getChat());
        $m->setUser($up->getChat()->getUser());
        $up->setMessage($m);
    }

    private function fromEntities(array $entities, Message $m, Update $up) {
        foreach ($entities as $entity) {
            if ($entity->getType() === "bot_command" && $m->getText()) {
                $name = substr($m->getText(), intval($entity->getOffset()) + 1, intval($entity->getOffset()) + intval($entity->getLength()));
                $arg = substr($m->getText(), intval($entity->getOffset()) + 1 + intval($entity->getLength()));
                if (!$com = $up->getCommand())
                    $com = new Command();
                $com->setName($name)->setArguments($arg);
                $up->setCommand($com);

                return;
            }
        }
    }

    private function fromLocationType(LocationType $lt = NULL, Message $m, Chat $chat) {
        if (!$lt)
            return NULL;
        $l = ( new Location())
                ->setLatitude($lt->getLatitude())
                ->setLongitude($lt->getLongitude());

        $chat->setLocation($l);
        $m->setLocation($l);
    }

    private function fromContactType(ContactType $ct = NULL, Message $m, Chat $chat) {
        if (!$ct)
            return NULL;
        $c = ( new Contact())
                ->setPhoneNumber(str_replace([" ", "+", "(", ")", "[", "]", "-"], "", $ct->getPhone_number()))
                ->setUser(( new User())
                ->setId($ct->getUser_id())
                ->setFirstName($ct->getFirst_name())
                ->setLastName($ct->getLast_name())
        );
        $chat->setContact($c);
        $m->setContact($c);
    }

    private function fromChatType(ChatType $cht, Chat $chat) {
        if (!$cht)
            return NULL;
        $chat->setId($cht->getId())
                ->setType($cht->getType())
                ->setUser(( new User())
                        ->setFirstName($cht->getFirst_name())
                        ->setLastName($cht->getLast_name())
                        ->setNickname($cht->getUsername())
                        ->setId($cht->getId())
        );
    }

    public function response(AnyType $anyType = NULL, $code = 200) {
        if (!headers_sent()) {
            $protocol = ( isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : "HTTP/1.0" );
            header($protocol . " " . $code . " " . http_response_code($code));
        }
        exit;
    }

    public function getFileLink(Link $l) {
        $query = "getFile?file_id=" . $l->getHref();
        $request = ( new \com\servandserv\data\curl\Request())
                ->setMethod("GET")
                ->setQuery($query)
                ->setSignature(hash_hmac("sha256", $query, $this->token));
        $resp = $this->cli->request($request);
        if ($json = json_decode($resp->getBody(), TRUE)) {
            if(isset($json["ok"]) && $json["ok"]=="true") {
                $rl = (new(Link))->setHref();
                return $rl;
            }
            throw new \Exception($json["description"], $json["error_code"]);
        }
    }

    private function getIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

}
