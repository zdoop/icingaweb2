<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

/**
 * Interface for HTTP clients
 */
interface ClientInterface
{
    /**
     * Set the maximum amount of redirects to follow
     *
     * @param   int     $maximum
     *
     * @return  $this
     */
    public function setMaximumRedirects($maximum);

    /**
     * Return the maximum amount of redirects to follow
     *
     * @return int
     */
    public function getMaximumRedirects();

    /**
     * Set the time in seconds before timeout
     *
     * @param   int     $timeout
     *
     * @return  $this
     */
    public function setTimeout($timeout);

    /**
     * Return the time in seconds before timeout
     *
     * @return int
     */
    public function getTimeout();

    /**
     * Send a request
     *
     * @param   RequestInterface    $request
     *
     * @return  ResponseInterface
     */
    public function sendRequest(RequestInterface $request);
}