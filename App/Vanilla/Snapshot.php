<?php

namespace App\Vanilla;

use GuzzleHttp\Client;

class Snapshot
{
    private $client;

    public function __construct()
    {
        $this->client = new Client(['verify' => getenv('DEBUG') == 'true']);
        //Genere le dossier minecraft/snapshot si il n'existe pas a la racine du projet
        if (!is_dir('minecraft/snapshot')) {
            mkdir('minecraft/snapshot', 0777, true);
        }

    }

    public function downloadVersions()
    {
        $data = $this->client->get('https://launchermeta.mojang.com/mc/game/version_manifest.json');
        if ($data->getStatusCode() !== 200) {
            $this->makeError('Can\'t retrieve Minecraft Snapshot versions list.');
        }
        //Transform body en object dans variable data
        $data = json_decode($data->getBody(), true);

        if (!isset($data['versions'])) {
            $this->makeError('Can\'t retrieve Minecraft Snapshot versions list.');
        }
        $versions = [];
        foreach ($data['versions'] as $version) {
            if (!isset($version['type']) || !isset($version['url']) || !$version['type']) {
                $this->makeError('Can\'t retrieve Minecraft Snapshot version type (snap or van).');
            }
            if ($version['type'] == 'snapshot' && version_compare($version['id'], "1.2.4", '>')) {
                $versionData = $this->getVersionData($version['url'], $version['id']);
                //Check if minecraft/snapshot/$version['id'] exists and don't get same sha1 as $versionData['sha1']
                if (file_exists("minecraft/snapshot/" . $version['id'])) {
                    $fileSha1 = sha1_file("minecraft/snapshot/" . $version['id']);

                    // Compare file sha1 with $versionData['sha1']
                    if ($fileSha1 == $versionData['sha1']) {
                        //If same, continue
                        echo "Snapshot: Same sha1 for " . $version['id'] . "\n";
                        continue;
                    }
                }
                //Download $versionData['url'] and save it in minecraft/snapshot/$version['id'] use Guzzle
                $download = $this->client->get($versionData['url'], ['sink' => "minecraft/snapshot/" . $version['id']]);
                //If download failed, makeError
                if (!file_exists("minecraft/snapshot/" . $version['id'])) {
                    $this->makeError("Can't download Minecraft Snapshot version " . $version['id'] . ".");
                }
                //Check if sha1 of downloaded file is same as $versionData['sha1']
                $fileSha1 = sha1_file("minecraft/snapshot/" . $version['id']);
                if ($fileSha1 !== $versionData['sha1']) {
                    //If not, makeError
                    $this->makeError("Can't download Minecraft Snapshot version " . $version['id'] . ".");
                }
                echo "Snapshot: Downloaded " . $version['id'] . "\n";
            }

        }
    }

    public function makeError($message)
    {
        http_response_code(500);
        header('Content-Type: application/json');
        $json = ['success' => false, 'message' => $message];
        //Die $json
        die(json_encode($json));
    }

    public function getVersionData($url, $id)
    {
        $versionData = $this->client->get($url);
        //Get json and return it
        if ($versionData->getStatusCode() !== 200) {
            $ver = $id;
            $this->makeError("Can't retrieve Minecraft Snapshot version $ver data.");
        }
        $versionData = json_decode($versionData->getBody(), true);

        if (!isset($versionData['downloads']) || !$versionData['downloads'] || !isset($versionData['downloads']['server']) || !$versionData['downloads']['server'] || !isset($versionData['downloads']['server']['sha1']) || !isset($versionData['downloads']['server']["url"]) || !$versionData['downloads']['server']["url"] || !$versionData['downloads']['server']['sha1']) {
            $ver = $id;
            $this->makeError("Can't retrieve Minecraft Snapshot server url of $ver.");
        }
        return ['url' => $versionData['downloads']['server']['url'], 'sha1' => $versionData['downloads']['server']['sha1']];
    }
}