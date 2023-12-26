<?php

namespace App\Sponge;

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
    #[NoReturn] protected function makeError($message): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        $json = ['success' => false, 'message' => $message];
        $mail = new \App\Mail();
        $mail->send('Error while get Sponge versions', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Sponge");
        //Die $json
        die(json_encode($json));
    }
    protected function downloadVersion($version, $type, $number, $actualnumber): void
    {
        $versionData = $this->getVersionData($version, $type);
        //Check if minecraft/$this->type/$version['id'] exists and don't get same sha1 as $versionData['sha1']
        if (file_exists("minecraft/$type/" . $version)) {
            $fileSha1 = sha1_file("minecraft/$type/" . $version);

            // Compare file sha1 with $versionData['sha1']
            if ($fileSha1 == $versionData['sha1']) {
                //If same, continue
                echo "$type: Same sha1 for " . $version . " ($actualnumber/$number)\n";
                return;
            }
        }
        //Download $versionData['url'] and save it in minecraft/$this->type/$version['id'] use Guzzle
        try {
            $this->client->get($versionData['url'], ['sink' => "minecraft/$type/" . $version]);
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Sponge Jar data', "Hello<br/> Ya une petite erreur avec $type.<br/> Error: $message <br/><br/> Type: Fabric");
            return;
        }
        //If download failed, makeError
        if (!file_exists("minecraft/$type/" . $version)) {
            $this->makeError("Can't download Minecraft $type version " . $version . ".");
        }
        //Check if sha1 of downloaded file is same as $versionData['sha1']
        $fileSha1 = sha1_file("minecraft/$type/" . $version);
        if ($fileSha1 !== $versionData['sha1']) {
            //If not, makeError
            $this->makeError("Can't download Minecraft $type version " . $version . ".");
        }
        echo "$type: Downloaded " . $version . " ($actualnumber/$number)\n";
    }
    protected function getVersionData($id, $type): array
    {
        try {
            $versionData = $this->client->get("https://dl-api.spongepowered.org/v2/groups/org.spongepowered/artifacts/$type/versions?recommended=false&limit=1&tags=,minecraft:$id");
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Sponge Versions data', "Hello<br/> Ya une petite erreur avec $type.<br/> Error: $message <br/><br/> Type: Fabric");
            return [];
        }
        //Get json and return it
        if ($versionData->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve Minecraft $type version $id data.");
        }
        $versionData = json_decode($versionData->getBody(), true);

        if (!isset($versionData['artifacts']) || !$versionData['artifacts']) {
            $this->makeError("Can't retrieve Minecraft $type server url of $id. 1");
        }
        $first_key = array_keys($versionData['artifacts'])[0];
        //Retreive version data
        try {
            $versionData = $this->client->get("https://dl-api.spongepowered.org/v2/groups/org.spongepowered/artifacts/$type/versions/$first_key");
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Sponge Jar data', "Hello<br/> Ya une petite erreur avec $type.<br/> Error: $message <br/><br/> Type: Fabric");
            return [];
        }
        if ($versionData->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve Minecraft $type version $id data.");
        }
        $versionData = json_decode($versionData->getBody(), true);
        //Check if there are the assets key
        if (!isset($versionData['assets']) || !$versionData['assets']) {
            $this->makeError("Can't retrieve Minecraft $type server url of $id. 2");
        }
        $classornot = version_compare($id, '1.12.2', '>');
        $version = [];
        foreach ($versionData['assets'] as $asset) {
            if (($classornot && $asset['classifier'] == 'universal') || (!$classornot && $asset['classifier'] == '' && $asset['extension'] == 'jar')) {
                $version = [
                    'url' => $asset['downloadUrl'],
                    'sha1' => $asset['sha1']
                ] ;
            }
        }

        if($version == []) {
            $this->makeError("Can't retrieve Minecraft $type server url of $id. 3");
        }
        return $version;
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
    protected function getVersions($type) {
        try {
            $data = $this->client->get("https://dl-api.spongepowered.org/v2/groups/org.spongepowered/artifacts/$type");
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Sponge McVersions data', "Hello<br/> Ya une petite erreur avec $type.<br/> Error: $message <br/><br/> Type: Fabric");
            return [];
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve Minecraft $type versions list.");
        }
        $data = json_decode($data->getBody(), true);
        //Check if tags key and tags|'minecraft' exist
        if (!isset($data['tags']) || !$data['tags'] || !isset($data['tags']['minecraft']) || !$data['tags']['minecraft']) {
            $this->makeError("Can't retrieve Minecraft $type versions list.");
        }
        return $data['tags']['minecraft'];
    }
}