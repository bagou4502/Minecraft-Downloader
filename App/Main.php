<?php

namespace App;

use App\Fabric\Fabric;
use App\Forge\Forge;
use App\Forge\NeoForge;
use App\Jenkins\Bungeecord;
use App\Mohist\Banner;
use App\Mohist\Mohist;
use App\Others\CatServers;
use App\Others\Magma;
use App\Paper\Folia;
use App\Paper\PaperMc;
use App\Paper\TraverTime;
use App\Paper\Velocity;
use App\Paper\WaterFall;
use App\Purpur\Purpur;
use App\Spigot\Spigot;
use App\Sponge\SpongeForge;
use App\Sponge\SpongeVanilla;
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
    private $purpur;
    private $spongevanilla;
    private $spongeforge;
    private $forge;
    private $neoforge;
    private $fabric;
    private $bungeecord;
    private $spigot;
    private $catservers;

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
        $this->purpur = new Purpur();
        $this->spongevanilla = new SpongeVanilla();
        $this->spongeforge = new SpongeForge();
        $this->forge = new Forge();
        $this->neoforge = new NeoForge();
        $this->fabric = new Fabric();
        $this->bungeecord = new BungeeCord();
        $this->spigot = new Spigot();
        $this->catservers = new CatServers();
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
        $this->catservers->downloadVersions();
        $this->spigot->downloadVersions();
        $this->bungeecord->downloadVersions();
        $this->fabric->downloadVersions();
        $this->neoforge->downloadVersions();
        $this->forge->downloadVersions();
        $this->spongeforge->downloadVersions();
        $this->spongevanilla->downloadVersions();
        $this->purpur->downloadVersions();
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
