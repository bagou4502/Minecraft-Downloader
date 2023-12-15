<?php

namespace App;

use App\Paper\Folia;
use App\Paper\PaperMc;
use App\Paper\TraverTime;
use App\Paper\Velocity;
use App\Paper\WaterFall;
use App\Vanilla\Snapshot;
use App\Vanilla\Vanilla;
use JetBrains\PhpStorm\NoReturn;
use Carbon\CarbonInterval;

class Main
{
    private $vanilla;
    private $snapshot;
    private $paper;
    private $folia;
    private $waterfall;
    private $velocity;
    private $travertime;

    public function __construct()
    {
        $this->vanilla = new Vanilla();
        $this->snapshot = new Snapshot();
        $this->paper = new PaperMc();
        $this->folia = new Folia();
        $this->waterfall = new WaterFall();
        $this->velocity = new Velocity();
        $this->travertime = new TraverTime();

        //Genere le dossier minecraft si il n'existe pas
        if (!file_exists('minecraft')) {
                mkdir('minecraft');
        }
        if (!file_exists('minecraft/getlist')) {
            mkdir('minecraft/getlist');
        }

    }

    public function downloadAll(): array
    {
        $time1 = microtime(true);

        $this->velocity->downloadVersions();
        $this->folia->downloadVersions();
        $this->waterfall->downloadVersions();
        $this->travertime->downloadVersions();
        $this->vanilla->downloadVersions();
        $this->snapshot->downloadVersions();
        $this->paper->downloadVersions();
        $time2 = microtime(true);
        $time = $time2 - $time1;
        $interval = CarbonInterval::seconds($time);
        $timeelapsed = $interval->cascade()->forHumans();
        http_response_code(200);

        $json = ['success' => true, 'message' => "All versions downloaded in $timeelapsed."];
        return $json;
    }
}
