<?php

namespace App\Others;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\NoReturn;

class Magma
{
    protected Client $client;
    protected $type = 'magma';

    public function __construct()
    {
        $this->client = new Client(['verify' => getenv('DEBUG') == 'true']);
        $this->type = 'magma';
        //Genere le dossier minecraft/$this->type si il n'existe pas a la racine du projet
        if (!is_dir("minecraft/$this->type")) {
            mkdir("minecraft/$this->type", 0777, true);
        }
    }
    protected function getVersions()
    {
        try {
            $data = $this->client->get("https://api.magmafoundation.org/api/v2/allVersions");
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Magma versions data', "Hello<br/> Ya une petite erreur avec Magma.<br/> Error: $message <br/><br/> Type: Fabric");
            return;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type  Versions.");
        }
        $data = json_decode($data->getBody(), true);
        $versions = [];
        foreach ($data as $version) {
            try {
                $request = $this->client->get("https://api.magmafoundation.org/api/v2/$version");
            } catch (GuzzleException $e) {
                $mail = new \App\Mail();
                $message = $e->getMessage();
                $mail->send('Error while get Magma Jar data', "Hello<br/> Ya une petite erreur avec Magma.<br/> Error: $message <br/><br/> Type: Fabric");
                return;
            }
            if ($request->getStatusCode() !== 200) {
                $this->makeError("Can't retrieve $this->type version $version.");
            }
            $request = json_decode($request->getBody(), true);
            if($request !== []) {
                $versions[] = $version;
            }
        }
        return $versions;
    }

    #[NoReturn] protected function makeError($message): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        $json = ['success' => false, 'message' => $message];
        $mail = new \App\Mail();
        $mail->send('Error while get Magma versions', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: MAGMA");
        //Die $json
        die(json_encode($json));
    }

    protected function downloadVersion($version, $number, $actualnumber): void
    {
        //Download $versionData['url'] and save it in minecraft/$this->type/$version['id'] use Guzzle
        $this->client->get("https://api.magmafoundation.org/api/v2/$version/latest/download", ['sink' => "minecraft/$this->type/$version"]);
        //If download failed, makeError
        if (!file_exists("minecraft/$this->type/$version")) {
            $this->makeError("Can't download Minecraft $this->type version $version.");
        }
        echo "$this->type: Downloaded $version ($actualnumber/$number)\n";
    }
    public function downloadVersions() {
        $versions = $this->getVersions();
        $number = count($versions);
        $actualnumber = 0;
        foreach ($versions as $version) {
            $actualnumber++;
            $this->downloadVersion($version, $number, $actualnumber);
        }
}



    protected function generateJson($data): void
    {
        //Enregistre le fichier minecraft/getlist/Vanilla.json avec try catch
        try {
            $name = ucfirst($this->type );
            file_put_contents("minecraft/getlist/$name.json", json_encode($data));
        } catch (\Throwable $th) {
            $this->makeError("Can't generate Minecraft $this->type  json.");
        }
    }
}