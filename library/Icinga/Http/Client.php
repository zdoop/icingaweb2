<?php

namespace Icinga\Http;

use Icinga\Application\Version;

class Client implements ClientInterface
{
    protected function getAgent()
    {
        $curlVersion = curl_version();
        $defaultAgent = 'ipl/' . Version::VERSION;
        $defaultAgent .= ' curl/' . $curlVersion['version'];

        return $defaultAgent;
    }

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

        if ($request->getPort() !== null) {
            curl_setopt($session, CURLOPT_PORT, $request->getPort());
        }

        curl_setopt_array($session, [
            CURLOPT_USERAGENT       => $this->getAgent(),
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_CUSTOMREQUEST   => $request->getMethod(),
            CURLOPT_SSL_VERIFYPEER  => $request->getVerifySSLPeer(),
            CURLOPT_FOLLOWLOCATION  => 1,
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_HTTP_VERSION    => $protocolVersion
        ]);

        $responseHeaders = [];
        curl_setopt($session, CURLOPT_HEADERFUNCTION, function($_, $header) use (&$responseHeaders)
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
        });

        $body = curl_exec($session);
        if ($body === false) {
            var_dump(curl_error($session));
        }

        $responseObject = new Response($responseHeaders, $body, curl_getinfo($session, CURLINFO_HTTP_CODE));
        curl_close($session);

        return $responseObject;
    }
}