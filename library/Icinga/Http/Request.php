<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

/**
 * Request class to send with a client
 */
class Request implements RequestInterface
{
    use MessageTrait;

    /**
     * URL to send the request to
     *
     * @var string
     */
    protected $url;

    /**
     * HTTP method to use
     *
     * @var string
     */
    protected $method;

    /**
     * HTTP version to use
     *
     * @var string
     */
    protected $protocolVersion = '1.1';

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
    public function __construct($method, $url, $headers = [])
    {
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers;
    }

    /**
     * Set a specific header
     *
     * @param   string  $header
     * @param   string  $value
     *
     * @return  $this
     */
    public function setHeader($header, $value)
    {
        $lowered = strtolower($header);
        $this->headerNames[$lowered] = $header;
        $this->headerValues[$lowered] = $value;

        return $this;
    }

    /**
     * Overwrite all headers
     *
     * @param   array   $headers
     *
     * @return  $this
     */
    public function setHeaders(array $headers)
    {
        $names = array_keys($headers);
        $lowered = array_map('strtolower', $names);

        $this->headerNames = array_combine(
            $lowered,
            $names
        );

        $this->headerValues = array_combine(
            $lowered,
            $headers
        );

        return $this;
    }

    /**
     * Set the body of this request
     *
     * @param   string  $body
     *
     * @return  $this
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
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