<?php

namespace App\Vanilla;

use GuzzleHttp\Client;

class Snapshot extends Controller
{
    private $type = 'snapshot';

    public function __construct()
    {
        parent::__construct();
        //Genere le dossier minecraft/$this->type si il n'existe pas a la racine du projet
        if (!is_dir("minecraft/$this->type")) {
            mkdir("minecraft/$this->type", 0777, true);
        }

    }

    public function downloadVersions()
    {
        $data = $this->getMinecraftData($this->type);
        $versions = [];
        foreach ($data['versions'] as $version) {
            if (!isset($version['type']) || !isset($version['url']) || !$version['type']) {
                $this->makeError("Can't retrieve Minecraft $this->type version type (snap or van).");
            }
            if ($version['type'] == $this->type) {
                $versions[] = $version;
            }

        }
        $versionsList = [];
        $number = count($versions);
        $actualnumber = 0;
        foreach ($versions as $version) {
            $actualnumber++;
            $downloader = new Controller();
            $downloader->Download($version, $this->type, $number, $actualnumber);
            $versionsList[] = [
                'name' => ucfirst($this->type) . " " . $version['id'],
                'version' => $version['id']
            ];
        }
        $this->generateJson($versionsList, $this->type);
    }
}