<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

use Icinga\Application\Version;

/**
 * HTTP client that uses cURL
 */
class CurlClient implements ClientInterface
{
    /**
     * Time in seconds before timeout
     *
     * @var int
     */
    protected $timeout = 10;

    /**
     * Maximum amount of redirects to follow
     *
     * @var int
     */
    protected $maximumRedirects = 20;

    /**
     * Whether to ignore SSL certificates
     *
     * @var bool
     */
    protected $ignoreSSL = false;

    /**
     * Return user agent
     *
     * @return  string
     */
    protected function getAgent()
    {
        $curlVersion = curl_version();
        $defaultAgent = 'ipl/' . Version::VERSION;
        $defaultAgent .= ' curl/' . $curlVersion['version'];

        return $defaultAgent;
    }

    /**
     * {@inheritdoc}
     */
    public function setMaximumRedirects($maximum)
    {
        $this->maximumRedirects = $maximum;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMaximumRedirects()
    {
        return $this->maximumRedirects;
    }

    /**
     * {@inheritdoc}
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * {@inheritdoc}
     */
    public function setIgnoreSSL($ignoreSSL)
    {
        $this->ignoreSSL = $ignoreSSL;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getIgnoreSSL()
    {
        return $this->ignoreSSL;
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request)
    {
        $session = curl_init($request->getUrl());

        // Bypass Except: 100-continue timeouts
        $headers = array('Except:');
        foreach ($request->getHeaders() as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }

        $protocolVersion = null;
        switch ($request->getProtocolVersion()) {
            case '2.0':
                $protocolVersion = CURL_HTTP_VERSION_2;
                break;
            case '1.1':
                $protocolVersion = CURL_HTTP_VERSION_1_1;
                break;
            default:
                $protocolVersion = CURL_HTTP_VERSION_1_0;
        }

        if ($this->getIgnoreSSL()) {
            $options[CURLOPT_SSL_VERIFYPEER] = 0;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        $options = [
            CURLOPT_USERAGENT       => $this->getAgent(),
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_CUSTOMREQUEST   => $request->getMethod(),
            CURLOPT_FOLLOWLOCATION  => 1,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_HTTP_VERSION    => $protocolVersion,
            CURLOPT_TIMEOUT         => $this->getTimeout(),
            CURLOPT_MAXREDIRS       => $this->getMaximumRedirects()
        ];

        if ($request->getPort() !== null) {
            $options[CURLOPT_PORT] = $request->getPort();
        }

        if ($request->getUsername() && $request->getPassword()) {
            $options[CURLOPT_USERPWD] = $request->getUsername() . ':' . $request->getPassword();
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        }


        $responseHeaders = [];
        $options[CURLOPT_HEADERFUNCTION] = function($_, $header) use (&$responseHeaders)
        {
            if (! trim($header)) {
                return strlen($header);
            }

            $headerParts = explode(': ', $header);
            if (strpos($headerParts[0], 'HTTP/') === 0) {
                $responseHeaders = [];
            } else {
                $responseHeaders[$headerParts[0]] = trim($headerParts[1]);
            }
            return strlen($header);
        };

        curl_setopt_array($session, $options);

        $body = curl_exec($session);
        if ($body === false) {
            var_dump(curl_error($session));
        }

        $responseObject = new Response($responseHeaders, $body, curl_getinfo($session, CURLINFO_HTTP_CODE));
        curl_close($session);

        return $responseObject;
    }
}