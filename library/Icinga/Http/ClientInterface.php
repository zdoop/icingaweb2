<?php
/* Icinga Web 2 | (c) 2017 Icinga Development Team | GPLv2+ */

namespace Icinga\Http;

interface ClientInterface
{
    public function sendRequest(RequestInterface $request);
}