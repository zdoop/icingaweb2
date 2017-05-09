<?php

namespace Icinga\Http;

interface ResponseInterface
{
    public function hasHeader($header);
    public function getHeader($header);
    public function getHeaders();
    public function getBody();
    public function getStatusCode();
}