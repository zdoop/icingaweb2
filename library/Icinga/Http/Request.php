<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

/**
 * Request class to send with a client
 */
class Request implements RequestInterface
{
    /**
     * URL to send the request to
     *
     * @var string
     */
    protected $url;

    /**
     * port to be added to the URL
     *
     * @var int
     */
    protected $port;

    /**
     * HTTP method to use
     *
     * @var string
     */
    protected $method;

    /**
     * Headers for this request
     *
     * @var array
     */
    protected $headers;

    /**
     * HTTP version to use
     *
     * @var string
     */
    protected $protocolVersion = '1.1';

    /**
     * TODO(jem): Okay to put a '?' here?
     * Should the SSL peer be verified?
     *
     * @var bool
     */
    protected $verifySSLPeer = true;

    /**
     * Username for basic authentication
     *
     * @var string
     */
    protected $username;

    /**
     * Password for basic authentication
     *
     * @var string
     */
    protected $password;

    /**
     * Request constructor.
     *
     * @param   string    $url
     * @param   string    $method
     * @param   array     $headers
     */
    public function __construct($url, $method = 'GET', $headers = [])
    {
        $this->url = $url;
        $this->method = $method;
        $this->headers = $headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * {@inheritdoc}
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function setPort($port)
    {
        $this->port = $port;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($header)
    {
        return $this->headers[$header];
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader($header)
    {
        return isset($this->headers[$header]);
    }

    /**
     * {@inheritdoc}
     */
    public function setHeader($header, $value)
    {
        $this->headers[$header] = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion()
    {
        return$this->protocolVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function setProtocolVersion($version)
    {
        $this->protocolVersion = $version;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setVerifySSLPeer($value)
    {
        $this->verifySSLPeer = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getVerifySSLPeer()
    {
        return $this->verifySSLPeer;
    }

    /**
     * {@inheritdoc}
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function send(ClientInterface $client = null)
    {
        if (! $client) {
            $client = new CurlClient();
        }
        return $client->sendRequest($this);
    }
}