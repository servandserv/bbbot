<?php

namespace com\servandserv\bot\domain\model;

interface View {

    public function getRequests();

    public function isSynchronous();
}
