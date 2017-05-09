<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

/**
 * Response object that contains information about a response
 */
class Response implements ResponseInterface
{
    /**
     * Headers of this response
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Body of this response
     *
     * @var string
     */
    protected $body;

    /**
     * Status code of this response
     *
     * @var int
     */
    protected $statusCode;

    /**
     * Response constructor.
     *
     * @param   array   $headers
     * @param   string  $body
     * @param   int     $statusCode
     */
    public function __construct(array $headers, $body, $statusCode)
    {
        $this->headers      = $headers;
        $this->body         = $body;
        $this->statusCode   = $statusCode;
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
    public function getBody()
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}