<?php

namespace App\Jenkins;

use GuzzleHttp\Client;
use JetBrains\PhpStorm\NoReturn;

class Bungeecord
{
    protected Client $client;
    protected string $type = 'bungeecord';

    protected function __construct()
    {
        $this->client = new Client(['verify' => getenv('DEBUG') == 'true']);
        //Genere le dossier minecraft/$this->type si il n'existe pas a la racine du projet
        if (!is_dir("minecraft/$this->type")) {
            mkdir("minecraft/$this->type", 0777, true);
        }


    }
    #[NoReturn] protected function makeError($message): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        $json = ['success' => false, 'message' => $message];
        //Die $json
        die(json_encode($json));
    }

    protected function getBuilds() {
        $data = $this->client->get('https://ci.md-5.net/job/BungeeCord/lastSuccessfulBuild/api/json');
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $data = json_decode($data->getBody(), true);
        if (!isset($data['builds']) || !$data['builds']) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        return $data['builds'];
    }
    protected function generateJson($versions, $type): void
    {
        $json = json_encode($versions);
        file_put_contents("minecraft/$type/versions.json", $json);
    }
    protected function downloadVersion($build): void {
        https://ci.md-5.net/view/SpigotMC/job/BungeeCord/1781/artifact/bootstrap/target/BungeeCord.jar
    }
    public function downloadVersions(): void
    {
        $versionData = $this->getBuilds();
        $versions = [];
        foreach ($versionData as $version) {

            $versions[] = [
                'name' => ucfirst($this->type) . " " . $version['number'],
                'version' => $version['number']
            ];
        }
        $this->generateJson($versions, $this->type);

        //Check if minecraft/$this->type/$version['id'] exists and don't get same sha1 as $versionData['sha1']

    }
}