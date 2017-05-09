<?php

namespace Icinga\Http;

class Response implements ResponseInterface
{
    protected $headers = array();
    protected $body;
    protected $statusCode;

    public function __construct(array $headers, $body, $statusCode)
    {
        $this->headers      = $headers;
        $this->body         = $body;
        $this->statusCode   = $statusCode;
    }

    public function getHeader($header)
    {
        return $this->headers[$header];
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function hasHeader($header)
    {
        return isset($this->headers[$header]);
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}