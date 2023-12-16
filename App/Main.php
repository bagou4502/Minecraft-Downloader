<?php

namespace App;

use App\Mohist\Banner;
use App\Mohist\Mohist;
use App\Others\Magma;
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
    private $mohist;
    private $banner;
    private $magma;

    //For sponge
    //https://repo.spongepowered.org/repository/maven-releases/org/spongepowered/spongevanilla/1.16.5-8.2.1-RC1369/spongevanilla-1.16.5-8.2.1-RC1369-universal.jar
    //https://dl-api.spongepowered.org/v2/groups/org.spongepowered/artifacts/spongevanilla/versions?tags=,minecraft:1.16.5&offset=0&limit=1
    //https://dl-api.spongepowered.org/v2/groups/org.spongepowered/artifacts/spongevanilla/versions?tags=,minecraft:1.16.5&offset=0&limit=1
    //https://spongepowered.org/downloads/spongeforge
    //https://dl-api.spongepowered.org/v2/groups/org.spongepowered/artifacts/spongeforge
    //Purpur
    //https://api.purpurmc.org/v2/purpur
    //https://api.purpurmc.org/v2/purpur/1.16.5/latest/download
    public function __construct()
    {
        $this->vanilla = new Vanilla();
        $this->snapshot = new Snapshot();
        $this->paper = new PaperMc();
        $this->folia = new Folia();
        $this->waterfall = new WaterFall();
        $this->velocity = new Velocity();
        $this->travertime = new TraverTime();
        $this->mohist = new Mohist();
        $this->banner = new Banner();
        $this->magma = new Magma();

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
        $this->magma->downloadVersions();
        $this->banner->downloadVersions();
        $this->mohist->downloadVersions();
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
