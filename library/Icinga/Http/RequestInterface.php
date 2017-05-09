<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

/**
 * Interface for HTTP requests
 */
interface RequestInterface
{
    /**
     * Return URL to send the request to
     *
     * @return  string
     */
    public function getUrl();

    /**
     * Return URL to send the request to
     *
     * @param   string  $url
     *
     * @return  $this
     */
    public function setUrl($url);

    /**
     * Return the port to be added to the URL
     *
     * @return  int
     */
    public function getPort();

    /**
     * Set the port to be added to the URL
     *
     * @param   int     $port
     *
     * @return  $this
     */
    public function setPort($port);

    /**
     * Return the HTTP method in use
     *
     * @return  string
     */
    public function getMethod();

    /**
     * Set the HTTP method in use
     *
     * @param   $method
     *
     * @return  $this
     */
    public function setMethod($method);

    /**
     * Return a specific header
     *
     * @param   $header
     *
     * @return  string
     */
    public function getHeader($header);

    /**
     * Return all headers
     *
     * @return  array
     */
    public function getHeaders();

    /**
     * Checks if a specific header exists
     *
     * @param   string  $header
     *
     * @return  bool
     */
    public function hasHeader($header);

    /**
     * Set a specific header
     *
     * @param   string  $header
     * @param   string  $value
     *
     * @return  $this
     */
    public function setHeader($header, $value);

    /**
     * Overwrite all headers
     *
     * @param   array   $headers
     *
     * @return  $this
     */
    public function setHeaders(array $headers);

    /**
     * Set the body of this request
     *
     * @param   string  $body
     *
     * @return  $this
     */
    public function setBody($body);

    /**
     * Return the body of this request
     *
     * @return  string
     */
    public function getBody();

    /**
     * Return HTTP version
     *
     * @return  string
     */
    public function getProtocolVersion();

    /**
     * Set HTTP version
     *
     * @param   string  $version
     *
     * @return  $this
     */
    public function setProtocolVersion($version);

    /**
     * Set username for basic authentication
     *
     * @param   string  $username
     *
     * @return  $this
     */
    public function setUsername($username);

    /**
     * Return username for basic authentication
     *
     * @return  string
     */
    public function getUsername();

    /**
     * Set password for basic authentication
     *
     * @param   string  $password
     *
     * @return  $this
     */
    public function setPassword($password);

    /**
     * Return the password for basic authentication
     *
     * @return  string
     */
    public function getPassword();

    /**
     * Send this request to a given client
     *
     * This uses {@see CurlClient} if no client is given.
     *
     * @param   ClientInterface|null    $client
     *
     * @return  ResponseInterface
     */
    public function send(ClientInterface $client = null);
}



