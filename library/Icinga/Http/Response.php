<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

/**
 * Response object that contains information about a response
 */
class Response implements ResponseInterface
{
    use MessageTrait;

    /**
     * Status code of this response
     *
     * @var int
     */
    protected $statusCode;

    /**
     * Response constructor.
     *
     * @param   int     $statusCode
     * @param   array   $headers
     * @param   string  $body
     */
    public function __construct($statusCode, array $headers, $body)
    {
        $this->statusCode = $statusCode;
        $this->setHeaders($headers);
        $this->body = $body;
    }

    /**
     * Overwrite all headers
     *
     * @param   array   $headers
     */
    protected function setHeaders($headers)
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
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}