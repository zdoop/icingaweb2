<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

/**
 * Interface for HTTP clients
 */
interface ClientInterface
{
    /**
     * Send a request
     *
     * @param   RequestInterface    $request
     *
     * @return  ResponseInterface
     */
    public function sendRequest(RequestInterface $request);
}