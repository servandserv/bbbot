<?php

namespace com\servandserv\bot\domain\model;

use \com\servandserv\data\curl\Request;

interface CurlClient {

    public function request(Request $req);

    public function getBody();

    public function getOptions();

    public function setOptions(array $options);
}
