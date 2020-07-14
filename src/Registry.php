<?php

namespace JAMS\IthenticateBundle;

class Registry
{
    private $clients;
    private $defaultClientName;
    public function __construct(array $clients, $defaultClientName)
    {
        $this->clients = $clients;
        $this->defaultClientName = $defaultClientName;
    }
}
