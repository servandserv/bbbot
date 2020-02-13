<?php

namespace com\servandserv\bot\infrastructure\domain\model\viber;

use \com\servandserv\bot\domain\model\View;

abstract class AbstractView implements View {

    protected $token;

    abstract public function getRequests();

    public function isSynchronous() {
        return FALSE;
    }

    public function setToken($token) {
        $this->token = $token;
    }

    protected function toJSON($xmlstr, $templ) {
        $str = $this->transform($xmlstr, $templ);
        if (!$json = json_decode($str, TRUE))
            throw new \Exception("Error on json in template " . $templ);
        return $json;
    }

    protected function transform($xmlstr, $templ) {
        $xml = new \DOMDocument();
        $xml->loadXML($xmlstr);
        $xsl = new \DOMDocument("1.0", "UTF-8");
        $xsl->loadXML(file_get_contents($templ));
        $xsl->documentURI = $templ;
        $xslProc = new \XSLTProcessor();
        $xslProc->importStylesheet($xsl);

        return $xslProc->transformToXML($xml);
    }

    protected function currencyEntity($code = "810") {
        $prefix = NULL;
        $suffix = NULL;
        switch ($code) {
            case "643":
            case "810":
                $suffix = "р.";
            case "840":
                $suffix = "$";
            case "826":
                $suffix = "£";
            case "978":
                $suffix = "€";
            default:
                $suffix = "р.";
        }

        return $suffix;
    }

    protected function money($sum, $code = "810") {
        return number_format(doubleval($sum), 2, ".", ",") . $this->currencyEntity($code);
    }

    protected function nbsp() {
        return utf8_encode(chr(160));
        return chr(0xc2) . chr(0xa0);
    }

    protected function getSignature($in, $token = "") {
        if ($token)
            $token = $token . "-" . $this->token;
        else
            $token = $this->token;
        $signature = hash_hmac("sha256", $in, $token);

        return $signature;
    }

}
