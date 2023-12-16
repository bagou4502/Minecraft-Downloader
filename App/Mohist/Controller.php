<?php

namespace App\Mohist;

use GuzzleHttp\Client;
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
        $data = $this->client->get("https://mohistmc.com/api/v2/projects/$type");
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
        //Die $json
        die(json_encode($json));
    }

    protected function downloadVersion($version, $type, $number, $actualnumber): void
    {
        $versionData = $this->getVersionData($version, $type);
        //Check if minecraft/$this->type/$version['id'] exists and don't get same sha1 as $versionData['sha1']
        if (file_exists("minecraft/$type/$version")) {
            $filemd5 = hash_file('md5', "minecraft/$type/$version");

            // Compare file sha1 with $versionData['sha1']
            if ($filemd5 == $versionData['md5']) {
                //If same, continue
                echo "$type: Same md5 for $version ($actualnumber/$number)\n";
                return;
            }
        }
        //Download $versionData['url'] and save it in minecraft/$this->type/$version['id'] use Guzzle
        $this->client->get($versionData['url'], ['sink' => "minecraft/$type/$version"]);
        //If download failed, makeError
        if (!file_exists("minecraft/$type/$version")) {
            $this->makeError("Can't download Minecraft $type version $version.");
        }
        //Check if sha1 of downloaded file is same as $versionData['sha1']
        $fileSha256 = hash_file('md5', "minecraft/$type/$version");
        if ($fileSha256 !== $versionData['md5']) {
            //If not, makeError
            $this->makeError("Can't download Minecraft $type version $version.");
        }
        echo "$type: Downloaded $version ($actualnumber/$number)\n";
    }

    protected function getVersionData($version, $type): array
    {
        $data = $this->client->get("https://mohistmc.com/api/v2/projects/$type/$version/builds");
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $type $version data.");
        }
        $data = json_decode($data->getBody(), true);
        $file = end($data['builds'])['url'];
        $md5 = end($data['builds'])['fileMd5'];
        return [
            'md5' => $md5,
            'url' => $file
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