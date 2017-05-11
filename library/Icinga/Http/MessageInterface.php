<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;


interface MessageInterface
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
     * @param   string  $header
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
     * Return the body of this request
     *
     * @return  string
     */
    public function getBody();
}