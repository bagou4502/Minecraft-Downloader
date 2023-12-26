<?php

namespace App\Sponge;

class SpongeVanilla extends Controller
{
    private $type = 'spongevanilla';


    public function __construct()
    {
        parent::__construct();

        //Genere le dossier minecraft/$this->type si il n'existe pas a la racine du projet
        if (!is_dir("minecraft/$this->type")) {
            mkdir("minecraft/$this->type", 0777, true);
        }
    }

    public function downloadVersions(): void
    {
        $data = $this->getVersions($this->type);
        $versions = [];
        $number = count($data);
        $actualnumber = 0;
        foreach ($data as $version) {
            $actualnumber++;
            $this->downloadVersion($version, $this->type, $number, $actualnumber);
            //Make array with name and version with a uppercase first letter of $this->type
            $versions[] = [
                'name' => ucfirst($this->type) . " " . $version,
                'version' => $version
            ];
        }
        $this->generateJson($versions, $this->type);
    }

}