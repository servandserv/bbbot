<?php

namespace com\servandserv\bot\infrastructure\domain\model\viber;

use \com\servandserv\bot\domain\model\CurlClient;
use \com\servandserv\bot\domain\model\CurlException;
use \com\servandserv\bot\domain\model\BotPort;
use \com\servandserv\bot\domain\model\RequestRepository;
use \com\servandserv\bot\domain\model\UserNotFoundException;
use \com\servandserv\bot\domain\model\NoSuitableDeviceException;
use \com\servandserv\bot\domain\model\View;
use \com\servandserv\happymeal\xml\schema\AnyType;
use \com\servandserv\data\bot\Request;
use \com\servandserv\data\bot\Update;
use \com\servandserv\data\bot\Updates;
use \com\servandserv\data\bot\Chat;
use \com\servandserv\data\bot\Delivery;
use \com\servandserv\data\bot\Read;
use \com\servandserv\data\bot\Location;
use \com\servandserv\data\bot\Message;
use \com\servandserv\data\bot\Link;
use \com\servandserv\data\bot\User;
use \com\viber\data\bot\Update as ViberUpdate;
use \com\viber\data\bot\SenderType;
use \com\viber\data\bot\UserType;

class BotAdapter implements BotPort {

    const CONTEXT = "com.viber";

    protected $cli;
    protected $NS;
    protected $token;
    protected $rep;
    protected static $updates;

    public function __construct(CurlClient $cli, $token, $NS, RequestRepository $rep, $uploadDir) {
        $this->token = $token;
        $this->NS = $NS;
        $this->cli = $cli;
        $this->rep = $rep;
        $this->uploadDir = $uploadDir;
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
        foreach ($requests as $request) {
            if ($this->rep->findBySignature($request->getSignature())) {
                continue;
            }
            $watermark = round(microtime(true) * 1000);
            $resp = $this->cli->request($request);
            $json = json_decode($resp->getBody(), TRUE);
            if ($json) {
                $status = $status_message = $message_token = NULL;
                if (array_key_exists("message_token", $json)) {
                    $message_token = $json["message_token"];
                }
                if (array_key_exists("status_message", $json)) {
                    $status_message = $json["status_message"];
                }
                if (array_key_exists("status", $json)) {
                    $status = intval($json["status"]);
                }
                if ($message_token && $status === 0) {
                    $ret = ( new Request())
                            ->setId($message_token)
                            ->setJson($request->getContent())
                            ->setSignature($request->getSignature())
                            ->setWatermark($watermark);
                    if ($cb) {
                        $cb($ret);
                    }
                } else if (in_array($status, [5, 6, 7])) {
                    // клиент отвалился или его никогда небыло скажем об этом миру
                    // https://developers.viber.com/api/rest-bot-api/index.html#errorCodes
                    throw new UserNotFoundException($status_message);
                } else if ($status === 11) {
                    // The receiver is using a device or a Viber version that don’t support public accounts.
                    // https://developers.viber.com/api/rest-bot-api/index.html#errorCodes
                    throw new NoSuitableDeviceException($status_message);
                } else {
                    // неизвестная ошибка, не плохо бы посмотреть на нее
                    throw new \Exception("Request\n" . $request->getContent() . "Response\n" . $resp->getBody());
                }
            }
        }
    }

    public function getUpdates() {
        if (NULL == self::$updates) {
            $in = file_get_contents("php://input");
            self::$updates = ( new Updates())->setContext(self::CONTEXT);
            if (!$json = json_decode($in, TRUE))
                throw new \Exception("Error on decode update json in " . __FILE__ . " on line " . __LINE__);
            // убрал проверку подписи надо с ней разбираться
            //if( !$this->checkSignature( json_encode( $json ) ) ) return self::$updates;
            $vup = ( new ViberUpdate())->fromJSONArray($json);
            $up = ( new Update())->setContext(self::CONTEXT)->setId(intval(microtime(true) * 1000))->setRaw($in);
            $chat = new Chat();
            switch ($vup->getEvent()) {
                case "webhook":
                    return self::$updates;
                    break;
                case "subscribed":
                    $up->setEvent("RECEIVED");
                    $chat->setId($vup->getUser()->getId());
                    $sender = $this->userFromUserType($vup->getUser());
                    $chat->setUser($sender);
                    $message = ( new Message())->setUser($sender)->setDt(intval(microtime(true) * 1000))->setId($up->getId());
                    $message->setText("help");
                    break;
                case "unsubscribed":
                    $up->setEvent("RECEIVED");
                    $chat->setId($vup->getUser_id());
                    return self::$updates;
                    break;
                case "conversation_started":
                    $up->setEvent("RECEIVED");
                    $chat->setId($vup->getUser()->getId());
                    $sender = $this->userFromUserType($vup->getUser());
                    $chat->setUser($sender);
                    $message = ( new Message())->setUser($sender)->setDt(intval(microtime(true) * 1000))->setId($up->getId());
                    $message->setText("help");
                    break;
                case "delivered":
                    $up->setEvent("DELIVERED");
                    $chat->setId($vup->getUser_id());
                    $del = ( new Delivery())->setMid($vup->getMessage_token())->setWatermark($vup->getTimestamp());
                    $up->setDelivery($del);
                    break;
                case "seen":
                    $up->setEvent("READ");
                    $chat->setId($vup->getUser_id());
                    $read = ( new Read())->setMid($vup->getMessage_token())->setWatermark($vup->getTimestamp());
                    $up->setRead($read);
                    break;
                case "failed":
                    throw new \Exception($vup->toXmlStr());
                    break;
                case "message":
                    $up->setEvent("RECEIVED");
                    $chat->setId($vup->getSender()->getId());
                    $sender = $this->userFromSenderType($vup->getSender());
                    $chat->setUser($sender);
                    $message = ( new Message())->setUser($sender)->setDt(intval(microtime(true) * 1000))->setId($up->getId());
                    $message->setText($vup->getMessage()->getText());
                    if ($l = $vup->getMessage()->getLocation()) {
                        $loc = ( new Location())->setLatitude($l->getLat())->setLongitude($l->getLon());
                        $chat->setLocation($loc);
                        $message->setLocation($loc);
                    }
                    if ($vup->getMessage()->getMedia()) {
                        $link = (new Link())
                            ->setHref($vup->getMessage()->getMedia())
                            ->setSize($vup->getMessage()->getSize())
                            ->setName($vup->getMessage()->getFile_name());
                        $message->setLink($link);
                    }
                    $up->setMessage($message);
                    break;
            }
            $up->setChat($chat->setContext(self::CONTEXT));
            $up->setIP($this->getIP());
            self::$updates->setUpdate($up);
        }
        return self::$updates;
    }

    private function userFromUserType(UserType $ut = null) {
        if (!$ut)
            return null;
        $u = ( new User())
                ->setId($ut->getId())
                ->setFirstName($ut->getName())
                ->setAvatar($ut->getAvatar())
                ->setLocale($ut->getLanguage());

        return $u;
    }

    private function userFromSenderType(SenderType $st = null) {
        if (!$st)
            return null;
        $u = ( new User())
                ->setId($st->getId())
                ->setFirstName($st->getName())
                ->setAvatar($st->getAvatar())
                ->setLocale($st->getLanguage());

        return $u;
    }

    public function response(AnyType $anyType = NULL, $code = 200) {
        if (!headers_sent()) {
            $protocol = ( isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : "HTTP/1.0" );
            header($protocol . " " . $code . " " . http_response_code($code));
        }
        exit;
    }

    public function downloadFile(Link $link) {
        $ch = curl_init($link->getHref());
        $fn = sha1($link->getHref());
        $fdir = 
        $fpath = $this->uploadDir . DIRECTORY_SEPARATOR . $fn;
        $fp = fopen($fpath, "w+");

        $ch = curl_init($link->getHref());
        $options = $this->cli->getOptions();
        $options[CURLOPT_RETURNTRANSFER] = 1;
        $options[CURLOPT_FILE] = $fp;
        foreach ($options as $name => $value) {
            curl_setopt($ch, $name, $value);
        }
        $curlData = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($statusCode!=200) {
            throw new \Exception("Status code:".$statusCode);
        }

        $mime = mime_content_type($fpath);
        $size = filesize($fpath);
        $temp = (new Link())
            ->setHref($fpath)
            ->setContent($mime)
            ->setSize($size)
            ->setName($link->getName());
        return $temp;
    }

    private function checkSignature($in) {
        $hash = hash_hmac("sha256", $in, $this->token);
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (substr($k, 0, 5) == "HTTP_") {
                $name = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($k, 5)))));
                $headers[$name] = $v;
                if ($name == "X-Viber-Content-Signature") {
                    return $hash == $v;
                }
            }
        }
    }

    private function getUserDetails($id) {
        $json = json_encode([
            "auth_token" => $this->token,
            "id" => $id
        ]);
        $request = ( new \com\servandserv\data\curl\Request())
                ->setMethod("POST")
                ->setQuery("get_user_details")
                ->setHeader(( new \com\servandserv\data\curl\Header())->setName("Content-type")->setValue("application/json"))
                ->setSignature($this->getSignature($json))
                ->setContent($json);
        $resp = $this->cli->request($request);
        if ($json = json_decode($resp->getBody(), TRUE)) {
            return $json;
        }
    }

    private function getSignature($in, $token = "") {
        if ($token)
            $token = $token . "-" . $this->token;
        else
            $token = $this->token;
        $signature = hash_hmac("sha256", $in, $token);

        return $signature;
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
