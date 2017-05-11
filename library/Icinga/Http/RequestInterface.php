<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

/**
 * Interface for HTTP requests
 */
interface RequestInterface extends MessageInterface
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



