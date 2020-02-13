<?php

namespace com\servandserv\bot\infrastructure\http;

use \com\servandserv\bot\domain\model\CurlClient;
use \com\servandserv\bot\domain\model\CurlException;
use \com\servandserv\bot\domain\model\events\Publisher;
use \com\servandserv\bot\domain\model\events\DebugEvent;
use \com\servandserv\bot\domain\model\events\ProfileEvent;
use \com\servandserv\data\curl\Request;

class GuzzleHttpClient implements CurlClient {

    protected $cli;
    protected $pubsub;
    protected $resp;
    protected $options;

    public function __construct(\GuzzleHttp\Client $cli, Publisher $pubsub) {
        $this->cli = $cli;
        $this->pubsub = $pubsub;
        $this->options = [];
    }

    public function request(Request $req) {
        $headers = [];
        $debugEvent = new DebugEvent("Request");
        foreach ($req->getHeader() as $header) {
            $headers[$header->getName()] = $header->getValue();
            $debugEvent->appendLog($header->getName() . ": " . $header->getValue());
        }
        $debugEvent->appendLog($req->getMethod() . " " . $this->cli->getConfig("base_uri") . $req->getQuery());
        $debugEvent->appendLog($req->getContent());
        $debugEvent->appendLog("Response");
        $profileEvent = new ProfileEvent($req->getMethod() . " " . $this->cli->getConfig("base_uri") . $req->getQuery());
        try {

            $this->resp = $this->cli->request($req->getMethod(), $req->getQuery(), ["headers" => $headers, "body" => $req->getContent()]);
            $this->pubsub->publish($profileEvent);

            foreach ($this->resp->getHeaders() as $name => $values) {
                $debugEvent->appendLog($name . ": " . implode(", ", $values));
            }
            $debugEvent->appendLog($this->resp->getBody());
            $this->pubsub->publish($debugEvent);

            return $this;
        } catch (\Exception $e) {
            $this->pubsub->publish($profileEvent);
            $debugEvent->appendLog($e->getCode() . ": " . $e->getMessage());
            if (method_exists($e, "hasResponse")) {
                $debugEvent->appendLog(\GuzzleHttp\Psr7\str($e->getResponse()));
            }
            $this->pubsub->publish($debugEvent);

            throw new CurlException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getEffectiveUrl($url, $max = 1) {
        $res = $this->cli->request("GET", $url, [
            "allow_redirects" => [
                "max" => $max, // allow at most 10 redirects.
                "strict" => true, // use "strict" RFC compliant redirects.
                "referer" => true, // add a Referer header
                "protocols" => ["https"], // only allow https URLs
                "track_redirects" => true
            ]
        ]);
        if ($redirect = $res->getHeaderLine("X-Guzzle-Redirect-History")) {
            return $redirect;
        } else {
            return $url;
        }
    }

    public function getBody() {
        if ($this->resp) {
            return $this->resp->getBody();
        }
        return NULL;
    }

    public function getOptions() {
        return $this->options;
    }
    
    public function setOptions(array $options) {
        $this->options = $options;
    }
}
