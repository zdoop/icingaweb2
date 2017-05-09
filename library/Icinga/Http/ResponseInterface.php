<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

/**
 * Interface for HTTP responses
 */
interface ResponseInterface
{
    /**
     * Checks if a specific header exists
     *
     * @param   string  $header
     *
     * @return  bool
     */
    public function hasHeader($header);

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
     * Return the body
     *
     * @return  array
     */
    public function getBody();

    /**
     * Return the HTTP status code
     *
     * @return mixed
     */
    public function getStatusCode();
}