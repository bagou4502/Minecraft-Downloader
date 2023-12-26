<?php

namespace App\Paper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\NoReturn;

class Controller
{
    protected Client $client;

    protected function __construct()
    {
        $this->client = new Client(['verify' => getenv('DEBUG') == 'true']);

    }
    protected function getVersions($type)
    {
        try {
            $data = $this->client->get("https://api.papermc.io/v2/projects/$type");
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Paper Versions data', "Hello<br/> Ya une petite erreur avec $type.<br/> Error: $message <br/><br/> Type: Fabric");
            return;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $type Versions.");
        }
        $data = json_decode($data->getBody(), true);
        if (!isset($data['versions']) || !$data['versions']) {
            $this->makeError("Can't retrieve $type Versions.");
        }
        return $data['versions'];
    }

    #[NoReturn] protected function makeError($message): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        $json = ['success' => false, 'message' => $message];
        $mail = new \App\Mail();
        $mail->send('Error while get PaperMc versions', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: PaperMc");
        //Die $json
        die(json_encode($json));
    }

    protected function downloadVersion($version, $type, $number, $actualnumber): void
    {
        $versionData = $this->getVersionData($version, $type);
        //Check if minecraft/$this->type/$version['id'] exists and don't get same sha1 as $versionData['sha1']
        if (file_exists("minecraft/$type/$version")) {
            $fileSha256 = hash_file('sha256', "minecraft/$type/$version");

            // Compare file sha1 with $versionData['sha1']
            if ($fileSha256 == $versionData['sha256']) {
                //If same, continue
                echo "$type: Same sha256 for $version ($actualnumber/$number)\n";
                return;
            }
        }
        //Download $versionData['url'] and save it in minecraft/$this->type/$version['id'] use Guzzle
        try {
            $this->client->get($versionData['url'], ['sink' => "minecraft/$type/$version"]);
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Paper Jar data', "Hello<br/> Ya une petite erreur avec $type.<br/> Error: $message <br/><br/> Type: Fabric");
            return;
        }
        //If download failed, makeError
        if (!file_exists("minecraft/$type/$version")) {
            $this->makeError("Can't download Minecraft $type version $version.");
        }
        //Check if sha1 of downloaded file is same as $versionData['sha1']
        $fileSha256 = hash_file('sha256', "minecraft/$type/$version");
        if ($fileSha256 !== $versionData['sha256']) {
            //If not, makeError
            $this->makeError("Can't download Minecraft $type version $version.");
        }
        echo "$type: Downloaded $version ($actualnumber/$number)\n";
    }

    protected function getVersionData($version, $type): array
    {
        $data = $this->client->get("https://api.papermc.io/v2/projects/$type/versions/$version/builds");
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $type $version data.");
        }
        $data = json_decode($data->getBody(), true);
        if (!isset($data['builds']) || !$data['builds'] || !isset($data['builds'][0]) || !$data['builds'][0] || !isset($data['builds'][0]['downloads']) || !$data['builds'][0]['downloads'] || !isset($data['builds'][0]['downloads']['application']) || !$data['builds'][0]['downloads']['application']) {
            $this->makeError("Can't retrieve $type $version data.");
        }
        $build = end($data['builds'])['build'];
        $file = end($data['builds'])['downloads']['application']['name'];
        return [
            'sha256' => end($data['builds'])['downloads']['application']['sha256'],
            'url' => "https://api.papermc.io/v2/projects/$type/versions/$version/builds/$build/downloads/$file"
        ];
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