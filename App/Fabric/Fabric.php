<?php

namespace App\Fabric;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\NoReturn;

class Fabric
{
    protected Client $client;
    protected string $type = 'fabric';

    public function __construct()
    {
        $this->client = new Client(['verify' => getenv('DEBUG') == 'true']);
        //Genere le dossier minecraft/$this->type si il n'existe pas a la racine du projet
        if (!is_dir("minecraft/$this->type")) {
            mkdir("minecraft/$this->type", 0777, true);
        }
    }

    public function downloadVersions()
    {
        $versions = $this->getVersions();
        $number = count($versions['versions']);
        $actualnumber = 0;
        $versionsList = [];
        foreach ($versions['versions'] as $version) {
            $actualnumber++;
            $this->downloadVersion($version, $this->type, $number, $actualnumber, $versions['loader'], $versions['installer']);
            $versionsList[] = [
                'name' => ucfirst($this->type) . " " . $version,
                'version' => $version,
                'loader' => $versions['loader'],
                'installer' => $versions['installer']
            ];
        }
        $this->generateJson($versionsList, $this->type);
    }

    protected function getVersions()
    {
        $data = [];
        try {
            $data = $this->client->get('https://meta.fabricmc.net/v2/versions/game');
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Fabric game data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Fabric");
            return null;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $data = json_decode($data->getBody(), true);
        $versions = ['versions' => []];
        foreach ($data as $version) {
            $versions['versions'][] = $version['version'];
        }
        try {
            $data = $this->client->get('https://meta.fabricmc.net/v2/versions/loader');
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Fabric loader data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Fabric");
            return null;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $data = json_decode($data->getBody(), true);
        $versions['loader'] = $data[0]['version'];
        try {
            $data = $this->client->get('https://meta.fabricmc.net/v2/versions/installer');
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Fabric installer data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Fabric");
            return null;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $data = json_decode($data->getBody(), true);
        $versions['installer'] = $data[0]['version'];
        return $versions;
    }

    #[NoReturn] protected function makeError($message): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        $json = ['success' => false, 'message' => $message];
        $mail = new \App\Mail();
        $mail->send('Error while get Fabric versions', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Fabric");
        //Die $json
        die(json_encode($json));
    }

    protected function downloadVersion($version, $type, $number, $actualnumber, $loader, $installer): void
    {
        //Check if minecraft/$this->type/$version['id'] exists and don't get same sha1 as $versionData['sha1']
        if (file_exists("minecraft/$type/" . $version)) {
            //Check minecraft/getlist/Fabric.json from files and check if a versionLong == $versionData['version'] exists
            $json = file_get_contents('./minecraft/getlist/Fabric.json');
            $json = json_decode($json, true);
            if ($json[0]['loader'] == $loader && $json[0]['installer'] == $installer) {
                echo "Fabric: Same version for " . $version . " ($actualnumber/$number)\n";
                return;
            };
        }
        //Download $versionData['url'] and save it in minecraft/$this->type/$version['id'] use Guzzle
        try {
            $this->client->get("https://meta.fabricmc.net/v2/versions/loader/$version/$loader/$installer/server/jar", ['sink' => "minecraft/$type/" . $version]);
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Fabric Jar data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Fabric");
            return;
        }
        //If download failed, makeError
        if (!file_exists("minecraft/$type/" . $version)) {
            $this->makeError("Can't download Minecraft $type version " . $version . ".");
        }
        echo "$type: Downloaded " . $version . " ($actualnumber/$number)\n";
    }

    protected function generateJson($data, $type): void
    {
        //Enregistre le fichier minecraft/getlist/Vanilla.json avec try catch
        try {
            $name = ucfirst($type);
            file_put_contents("minecraft/getlist/$name.json", json_encode($data));
        } catch (\Throwable $th) {
            $this->makeError("Can't generate Minecraft $type json.");
        }
    }

}