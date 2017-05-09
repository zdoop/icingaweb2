<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

class Request implements RequestInterface
{
    protected $url;
    protected $port;
    protected $method;
    protected $headers;
    protected $protocolVersion = '1.1';
    protected $verifySSLPeer = true;

    public function __construct($url, $method = 'GET', $headers = [])
    {
        $this->url = $url;
        $this->method = $method;
        $this->headers = $headers;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function setPort($port)
    {
        $this->port = $port;
        return $this;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
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

    public function setHeader($header, $value)
    {
        $this->headers[$header] = $value;
        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    public function getProtocolVersion()
    {
        return$this->protocolVersion;
    }

    public function setProtocolVersion($version)
    {
        $this->protocolVersion = $version;
        return $this;
    }

    public function setVerifySSLPeer($value)
    {
        $this->verifySSLPeer = $value;
        return $this;
    }

    public function getVerifySSLPeer()
    {
        return $this->verifySSLPeer;
    }

    public function send(ClientInterface $client = null)
    {
        if (! $client) {
            $client = new Client();
        }
        return $client->sendRequest($this);
    }
}