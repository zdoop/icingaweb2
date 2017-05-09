<?php

namespace Icinga\Http;

interface RequestInterface
{
    public function getUrl();

    /**
     * @param   \Icinga\Web\Url|string $url
     * @return  $this
     */
    public function setUrl($url);
    public function getPort();
    public function setPort($port);
    public function getMethod();
    public function setMethod($method);
    public function hasHeader($header);
    public function getHeader($header);
    public function getHeaders();
    public function setHeader($header, $value);
    public function setHeaders(array $headers);
    public function getProtocolVersion();
    public function setProtocolVersion($version);
    public function setVerifySSLPeer($value);
    public function getVerifySSLPeer();
    public function send(ClientInterface $client = null);
    // auth
}



