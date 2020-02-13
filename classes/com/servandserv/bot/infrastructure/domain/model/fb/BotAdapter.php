<?php

namespace com\servandserv\bot\infrastructure\domain\model\fb;

use \com\servandserv\bot\domain\model\BotPort;
use \com\servandserv\bot\domain\model\CurlClient;
use \com\servandserv\bot\domain\model\RequestRepository;
use \com\servandserv\happymeal\XML\Schema\AnyType;
use \com\servandserv\data\bot\Updates;
use \com\servandserv\data\bot\Update;
use \com\servandserv\data\bot\UpdateEventType;
use \com\servandserv\data\bot\Request;
use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\Message;
use \com\servandserv\data\bot\Delivery;
use \com\servandserv\data\bot\Read;
use \com\servandserv\data\bot\Command;
use \com\servandserv\data\bot\Contact;
use \com\servandserv\data\bot\Location;
use \com\facebook\data\bot\Update as FacebookUpdate;

class BotAdapter implements BotPort {

    const CONTEXT = "com.facebook";

    protected $cli;
    protected $NS;
    protected $secret;
    protected $rep;
    protected static $updates;

    public function __construct(CurlClient $cli, $secret, $NS, RequestRepository $rep) {
        $this->cli = $cli;
        $this->NS = $NS;
        $this->secret = $secret;
        $this->rep = $rep;
    }

    public function makeRequest($name, array $args, callable $cb = NULL) {
        $clName = $this->NS . "\\" . $name;
        if (!class_exists($clName))
            throw new \Exception("Class for VIEW name \"$name\" not exists.");
        $cl = new \ReflectionClass($clName);
        $view = call_user_func_array(array(&$cl, 'newInstance'), $args);

        try {
            $requests = $view->getRequests();
            foreach ($requests as $request) {
                // если идентичный запрос уже отправляли, то пропускаем
                if ($this->rep->findBySignature($request->getSignature()))
                    continue;

                $watermark = round(microtime(true) * 1000);
                $resp = $this->cli->request($request);
                if ($json = json_decode($resp->getBody(), TRUE)) {
                    if (isset($json["message_id"])) {
                        $ret = ( new Request())
                                ->setId($json["message_id"])
                                ->setJson($request->getContent())
                                ->setWatermark($watermark);
                        if ($cb)
                            $cb($ret);
                    } else if (isset($json["error"])) {
                        throw new \Exception($json["error"]["message"], $json["error"]["code"] . "." . $json["error"]["subcode"]);
                    }
                }
            }
        } catch (\Exception $e) {
            switch ($e->getCode()) {
                case "100.":
                case "100.2018001":
                    throw new UserNotFoundException($e->getMessage(), $e->getCode());
                    break;
                default:
                    //trigger_error( $e->getMessage() );
                    throw new \Exception($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    public function getUpdates() {
        if (NULL == self::$updates) {
            $in = file_get_contents("php://input");
            self::$updates = ( new Updates())->setContext(self::CONTEXT);
            if (!$json = json_decode($in, TRUE))
                throw new \Exception("Error on decode update json in " . __FILE__ . " on line " . __LINE__);
            //if( !$this->auth( $json ) ) throw new \Exception( "Invalid X-Hub-Signature header in ".__FILE__." on ".__LINE__ );
            $fbup = ( new FacebookUpdate())->fromJSONArray($json);
            $entries = $fbup->getEntry();
            foreach ($entries as $entry) {
                $items = $entry->getMessaging();
                foreach ($items as $item) {
                    $up = ( new Update())
                            ->setContext(self::CONTEXT)
                            ->setId(intval(microtime(true) * 1000))
                            ->setRaw($in);
                    $chat = ( new Chat())
                            ->setId($item->getSender()->getId())
                            ->setType("private")
                            ->setContext(self::CONTEXT);
                    if ($item->getDelivery()) {
                        $up->setEvent(UpdateEventType::_DELIVERED);
                        $delivery = ( new Delivery())
                                ->setWatermark($item->getDelivery()->getWatermark())
                                ->setSeq($item->getDelivery()->getSeq());
                        $mids = $item->getDelivery()->getMids();
                        foreach ($mids as $mid) {
                            $delivery->setMid($mid);
                        }
                        $up->setDelivery($delivery);
                    }
                    if ($item->getRead()) {
                        $up->setEvent(UpdateEventType::_READ);
                        $read = ( new Read())
                                ->setWatermark($item->getRead()->getWatermark())
                                ->setSeq($item->getRead()->getSeq());
                    }
                    if ($item->getMessage()) {
                        $up->setEvent(UpdateEventType::_RECEIVED);
                        $message = ( new Message())
                                ->setId($item->getMessage()->getMid())
                                ->setDt($item->getTimestamp())
                                ->setText($item->getMessage()->getText());
                        $attachments = $item->getMessage()->getAttachments();
                        foreach ($attachments as $attachment) {
                            switch ($attachment->getType()) {
                                case "location":
                                    $loc = ( new Location())
                                            ->setLatitude($attachment->getPayload()->getCoordinates()->getLat())
                                            ->setLongitude($attachment->getPayload()->getCoordinates()->getLong());
                                    $message->setLocation($loc);
                                    $chat->setLocation($loc);
                            }
                        }
                        $up->setMessage($message);
                    }
                    $up->setChat($chat);
                    if ($item->getPostback()) {
                        $up->setEvent(UpdateEventType::_POSTBACK);
                        $command = ( new Command())
                                ->setName(substr($item->getPostback()->getPayload(), 1))
                                ->setArguments("");
                        $up->setCommand($command);
                    }
                    if ($item->getAccount_linking()) {
                        $up->setEvent(UpdateEventType::_POSTBACK);
                        $command = ( new Command())
                                ->setName("account-" . $item->getAccount_linking()->getStatus())
                                ->setArguments($item->getAccount_linking()->getAuthorization_code());
                        $up->setCommand($command);
                    }
                    self::$updates->setUpdate($up);
                }
            }
        }
        //print self::$updates->toXmlStr();exit;
        return self::$updates;
    }

    public function response(AnyType $anyType = NULL, $code = 200) {
        if (!headers_sent()) {
            $protocol = ( isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : "HTTP/1.0" );
            header($protocol . " " . $code . " " . http_response_code($code));
        }
        exit;
    }

    private function auth($json) {
        $in = json_encode($json, JSON_UNESCAPED_UNICODE);
        if (isset($_SERVER["HTTP_X_HUB_SIGNATURE"]) &&
                $_SERVER["HTTP_X_HUB_SIGNATURE"] === "sha1=" . hash_hmac("sha1", $in, $this->secret)
        ) {
            return TRUE;
        }
        return FALSE;
    }

}
