<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

/**
 * Trait for requests and responses
 */
trait MessageTrait
{
    /**
     * Case sensitive header names with lowercase header names as keys
     *
     * @var array
     */
    protected $headerNames = [];

    /**
     * Header values with lowercase header names as keys
     *
     * @var array
     */
    protected $headerValues = [];

    /**
     * The body of this request
     *
     * @var string
     */
    protected $body;

    /**
     * Return a specific header
     *
     * @param   string  $header
     *
     * @return  string
     */
    public function getHeader($header)
    {
        $lowered = strtolower($header);
        if (isset($this->headerValues[$lowered])) {
            return $this->headerValues[$lowered];
        }

        return null;
    }

    /**
     * Return all headers
     *
     * @return  array
     */
    public function getHeaders()
    {
        return array_combine($this->headerNames, $this->headerValues);
    }

    /**
     * Checks if a specific header exists
     *
     * @param   string  $header
     *
     * @return  bool
     */
    public function hasHeader($header)
    {
        return isset($this->headerValues[strtolower($header)]);
    }

    /**
     * Return the body of this request
     *
     * @return  string
     */
    public function getBody()
    {
        return $this->body;
    }
}