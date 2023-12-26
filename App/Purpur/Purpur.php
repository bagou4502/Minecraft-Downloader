<?php

namespace App\Purpur;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\NoReturn;

class Purpur
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client(['verify' => getenv('DEBUG') == 'true']);
        if (!is_dir("minecraft/purpur")) {
            mkdir("minecraft/purpur", 0777, true);
        }
    }
    public function downloadVersions(): void
    {
        $data = $this->getVersions('purpur');
        $versions = [];
        $number = count($data);
        $actualnumber = 0;
        foreach ($data as $version) {
            $actualnumber++;
            $this->downloadVersion($version, 'purpur', $number, $actualnumber);
            //Make array with name and version with a uppercase first letter of $this->type
            $versions[] = [
                'name' => ucfirst('purpur') . " " . $version,
                'version' => $version
            ];
        }
        $this->generateJson($versions, 'purpur');
    }
    protected function getVersions($type)
    {
        try {
            $data = $this->client->get("https://api.purpurmc.org/v2/purpur");
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Purpur versions data', "Hello<br/> Ya une petite erreur avec $type.<br/> Error: $message <br/><br/> Type: Fabric");
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
        $mail->send('Error while get Purpur versions', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Purpur");
        //Die $json
        die(json_encode($json));
    }
    public function getVersionData($version, $type) {
        try {
            $data = $this->client->get("https://api.purpurmc.org/v2/purpur/$version");
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Purpur version data', "Hello<br/> Ya une petite erreur avec $type.<br/> Error: $message <br/><br/> Type: Fabric");
            return;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $type version $version.");
        }
        $data = json_decode($data->getBody(), true);
        if (!isset($data['builds']) || !$data['builds'] || !isset($data['builds']['latest']) || !$data['builds']['latest']) {
            $this->makeError("Can't retrieve $type version $version.");
        }
        $build = $data['builds']['latest'];
        try {
            $data = $this->client->get("https://api.purpurmc.org/v2/purpur/$version/$build");
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Purpur Jar data', "Hello<br/> Ya une petite erreur avec $type.<br/> Error: $message <br/><br/> Type: Fabric");
            return;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $type version $version.");
        }
        $data = json_decode($data->getBody(), true);
        if (!isset($data['md5']) || !$data['md5']) {
            $this->makeError("Can't retrieve $type version $version.");
        }
        return [
            'url' => "https://api.purpurmc.org/v2/purpur/$version/$build/download",
            'md5' => $data['md5']
        ];
    }
    protected function downloadVersion($version, $type, $number, $actualnumber): void
    {
        $versionData = $this->getVersionData($version, $type);
        if (file_exists("minecraft/$type/$version")) {
            $fileMd5 = hash_file('md5', "minecraft/$type/$version");
            if ($fileMd5 == $versionData['md5']) {
                echo "$type: Same md5 for $version ($actualnumber/$number)\n";
                return;
            }
        }
        $this->client->get($versionData['url'], ['sink' => "minecraft/$type/$version"]);
        //If download failed, makeError
        if (!file_exists("minecraft/$type/$version")) {
            $this->makeError("Can't download Minecraft $type version $version.");
        }
        //Check if sha1 of downloaded file is same as $versionData['sha1']
        $fileMd5 = hash_file('md5', "minecraft/$type/$version");
        if ($fileMd5 !== $versionData['md5']) {
            //If not, makeError
            $this->makeError("Can't download Minecraft $type version $version.");
        }
        echo "$type: Downloaded $version ($actualnumber/$number)\n";
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