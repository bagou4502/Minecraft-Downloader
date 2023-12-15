<?php

namespace App;

class Main
{
    private $servers = [];

    public function __construct()
    {
        $this->servers[] = new Vanilla();

    }

    public function downloadAll($version)
    {
        foreach($this->servers as $server)
        {
            $server->downloadVersion($version);
        }
    }
}
