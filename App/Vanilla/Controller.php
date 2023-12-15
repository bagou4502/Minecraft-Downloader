<?php

namespace App\Vanilla;

use GuzzleHttp\Client;
use JetBrains\PhpStorm\NoReturn;

class Controller
{
    protected Client $client;

    protected function __construct()
    {
        $this->client = new Client(['verify' => getenv('DEBUG') == 'true']);

    }
    #[NoReturn] protected function makeError($message): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        $json = ['success' => false, 'message' => $message];
        //Die $json
        die(json_encode($json));
    }
    protected function Download($version, $type, $number, $actualnumber): void
    {
        $versionData = $this->getVersionData($version['url'], $version['id'], $type);
        //Check if minecraft/$this->type/$version['id'] exists and don't get same sha1 as $versionData['sha1']
        if (file_exists("minecraft/$type/" . $version['id'])) {
            $fileSha1 = sha1_file("minecraft/$type/" . $version['id']);

            // Compare file sha1 with $versionData['sha1']
            if ($fileSha1 == $versionData['sha1']) {
                //If same, continue
                echo "$type: Same sha1 for " . $version['id'] . " ($actualnumber/$number)\n";
                return;
            }
        }
        //Download $versionData['url'] and save it in minecraft/$this->type/$version['id'] use Guzzle
        $this->client->get($versionData['url'], ['sink' => "minecraft/$type/" . $version['id']]);
        //If download failed, makeError
        if (!file_exists("minecraft/$type/" . $version['id'])) {
            $this->makeError("Can't download Minecraft $type version " . $version['id'] . ".");
        }
        //Check if sha1 of downloaded file is same as $versionData['sha1']
        $fileSha1 = sha1_file("minecraft/$type/" . $version['id']);
        if ($fileSha1 !== $versionData['sha1']) {
            //If not, makeError
            $this->makeError("Can't download Minecraft $type version " . $version['id'] . ".");
        }
        echo "$type: Downloaded " . $version['id'] . " ($actualnumber/$number)\n";
    }
    protected function getVersionData($url, $id, $type): array
    {
        $versionData = $this->client->get($url);
        //Get json and return it
        if ($versionData->getStatusCode() !== 200) {
            $ver = $id;
            $this->makeError("Can't retrieve Minecraft $type version $ver data.");
        }
        $versionData = json_decode($versionData->getBody(), true);

        if (!isset($versionData['downloads']) || !$versionData['downloads'] || !isset($versionData['downloads']['server']) || !$versionData['downloads']['server'] || !isset($versionData['downloads']['server']['sha1']) || !isset($versionData['downloads']['server']["url"]) || !$versionData['downloads']['server']["url"] || !$versionData['downloads']['server']['sha1']) {
            $ver = $id;
            $this->makeError("Can't retrieve Minecraft $type server url of $ver.");
        }
        return ['url' => $versionData['downloads']['server']['url'], 'sha1' => $versionData['downloads']['server']['sha1']];
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
    protected function getMinecraftData($type) {
        $data = $this->client->get('https://launchermeta.mojang.com/mc/game/version_manifest.json');
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve Minecraft $type versions list.");
        }
        //Transform body en object dans variable data
        $data = json_decode($data->getBody(), true);

        if (!isset($data['versions'])) {
            $this->makeError("Can't retrieve Minecraft $type versions list.");
        }
        return $data;
    }
}