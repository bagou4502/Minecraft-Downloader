<?php

namespace App\Jenkins;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\NoReturn;

class Bungeecord
{
    protected Client $client;
    protected string $type = 'bungeecord';
    protected array $ignoredVersions = [37,38,39,70,92,102,141,147,176,172,173,298,299,377,378,453,470,513,538,562,584,607,679,733,789,1003,1004,1106,1107,1218,1240,1272,1648];
    public function __construct()
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
        $mail = new \App\Mail();
        $mail->send('Error while get Bungeecord versions', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Bungeecord");
        //Die $json
        die(json_encode($json));
    }

    protected function getLatestBuild() {
        try {
        $data = $this->client->get('https://ci.md-5.net/job/BungeeCord/lastSuccessfulBuild/api/json');
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Bungeecord Versions data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Bungeecord");
            return;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $data = json_decode($data->getBody(), true);
        if (!isset($data['id']) || !$data['id']) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        return $data['id'];
    }
    protected function generateJson($versions, $type): void
    {
        try {
            $name = ucfirst($type);
            file_put_contents("minecraft/getlist/$name.json", json_encode($versions));
        } catch (\Throwable $th) {
            $this->makeError("Can't generate Minecraft $type json.");
        }
    }
    protected function downloadVersion($type, $number, $actualnumber): void {
        //Check if the file exists in minecraft/$this->type/$version['id']
        if (file_exists("minecraft/$type/" . $actualnumber)) {
            echo "$type: Already downloaded " . $actualnumber . " ($actualnumber/$number)\n";
            return;
        }
        //Download https://ci.md-5.net/job/BungeeCord/$version['id']/artifact/bootstrap/target/BungeeCord.jar and save it in minecraft/$this->type/$version['id'] use Guzzle and try catch
        try {
            if($actualnumber > 679) {
                $this->client->get("https://ci.md-5.net/job/BungeeCord/$actualnumber/artifact/bootstrap/target/BungeeCord.jar", ['sink' => "minecraft/$type/" . $actualnumber]);
            } else if($actualnumber > 101) {
                $this->client->get("https://ci.md-5.net/job/BungeeCord/$actualnumber/artifact/proxy/target/BungeeCord.jar", ['sink' => "minecraft/$type/" . $actualnumber]);
            } else {
                $this->client->get("https://ci.md-5.net/job/BungeeCord/$actualnumber/artifact/target/BungeeCord.jar", ['sink' => "minecraft/$type/" . $actualnumber]);
            }
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while downloading Bungeecord', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: $type <br/> Number: $number <br/> Actualnumber: $actualnumber");
            echo "$type: Can't download " . $actualnumber . " ($actualnumber/$number)\n";
            return;
        }
        //If download failed, makeError
        if (!file_exists("minecraft/$type/" . $actualnumber)) {
            $this->makeError("Can't download Minecraft $type version " . $actualnumber . ".");
        }
        echo "$type: Downloaded " . $actualnumber . " ($actualnumber/$number)\n";
    }
    public function downloadVersions() {
        $number = $this->getLatestBuild();
        $actualnumber = 0;
        $versionsList = [];

        while ($number >= $actualnumber) {
            $actualnumber++;
            if(in_array($actualnumber, $this->ignoredVersions) || $actualnumber > $number) continue;
            $this->downloadVersion($this->type, $number, $actualnumber);
            $minecraftCompatibleFrom = '1.8';
            $minecraftCompatibleTo = 'latest';
            if($actualnumber <= 251) {
                $minecraftCompatibleFrom = '1.0';
                $minecraftCompatibleTo = '1.4.7';
            } elseif($actualnumber <= 386) {
                $minecraftCompatibleFrom = '1.5.0';
                $minecraftCompatibleTo = '1.5.0';
            } elseif($actualnumber <= 548) {
                $minecraftCompatibleFrom = '1.5.1';
                $minecraftCompatibleTo = '1.5.2';
            } elseif($actualnumber <= 666) {
                $minecraftCompatibleFrom = '1.6.2';
                $minecraftCompatibleTo = '1.6.2';
            } elseif($actualnumber <= 701) {
                $minecraftCompatibleFrom = '1.6.4';
                $minecraftCompatibleTo = '1.6.4';
            } elseif($actualnumber <= 1119) {
                $minecraftCompatibleFrom = '1.7.2';
                $minecraftCompatibleTo = '1.7.10';
            }
            $versionsList[] = [
                'name' => ucfirst($this->type) . " " . $actualnumber,
                'version' => $actualnumber,
                'from' => $minecraftCompatibleFrom,
                'to' => $minecraftCompatibleTo
            ];
        }
        $this->generateJson($versionsList, $this->type);
    }
}