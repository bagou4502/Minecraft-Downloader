<?php

namespace App;

use App\Vanilla\Snapshot;
use App\Vanilla\Vanilla;

class Main
{
    private $vanilla;
    private $snapshot;
    public function __construct()
    {
        $this->vanilla = new Vanilla();
        $this->snapshot = new Snapshot();

        //Genere le dossier minecraft si il n'existe pas
        if (!file_exists('minecraft')) {
                mkdir('minecraft');
        }

    }

    public function downloadAll()
    {
        $this->vanilla->downloadVersions();
        $this->snapshot->downloadVersions();
        return;
    }
}
