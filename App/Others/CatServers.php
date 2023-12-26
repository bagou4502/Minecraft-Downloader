<?php

namespace App\Others;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\NoReturn;

class CatServers
{
    //https://jenkins.rbqcloud.cn:30011/api/json
    //https://jenkins.rbqcloud.cn:30011/job/CatServer-1.16.5/lastSuccessfulBuild/api/json

    protected Client $client;
    protected string $type = 'catservers';

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
        $jobs = $this->getJobs();
        $number = count($jobs);
        $actualnumber = 0;
        $versionsList = [];

        foreach ($jobs as $job) {
            $actualnumber++;
            $version = $this->getJobData($job);
            $this->downloadVersion($version, $this->type, $number, $actualnumber);
            $versionsList[] = [
                'name' => ucfirst($this->type) . " " . $version['version'],
                'version' => $version['version'],
                'id' => $version['id']
            ];
        }
        $this->generateJson($versionsList, $this->type);
    }

    protected function downloadVersion($version, $type, $number, $actualnumber): void
    {
        //Check if the file exists in minecraft/$this->type/$version['id']
        if (file_exists("minecraft/$type/" . $version['version'])) {
            $json = file_get_contents('./minecraft/getlist/Catservers.json');
            $exists = in_array($version['id'], array_column(json_decode($json, true), 'id'));
            if($exists) {
                echo "$type: Already downloaded " . $version['version'] . " ($actualnumber/$number)\n";
                return;
            };
        }

        try {
            $this->client->get($version['url'], ['sink' => "minecraft/$type/" . $version['version']]);
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while downloading CatServers', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: $type <br/> Number: $number <br/> Version: " . $version['version']);
            echo "$type: Can't download " . $version['version'] . " ($actualnumber/$number)\n";
            return;
        }
        //If download failed, makeError
        if (!file_exists("minecraft/$type/" . $version['version'])) {
            $this->makeError("Can't download Minecraft $type version " . $version['version'] . ".");
        }
        echo "$type: Downloaded " . $version['version'] . " ($actualnumber/$number)\n";
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

    protected function getJobs()
    {
        try {
            $data = $this->client->get('https://jenkins.rbqcloud.cn:30011/api/json');
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get CatServers Versions data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: CatServers");
            return null;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $data = json_decode($data->getBody(), true);
        if (!isset($data['jobs']) || !$data['jobs']) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $jobs = [];
        foreach ($data['jobs'] as $job) {
            if (strpos($job['name'], 'CatServer') !== false) {
                $jobs[] = $job['name'];
            }
        }
        //Check if jobs is not empty
        if ($jobs == []) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $jobs = array_reverse($jobs);
        return $jobs;
    }
    public function getJobData(string $job) {
        try {
            $data = $this->client->get("https://jenkins.rbqcloud.cn:30011/job/$job/lastSuccessfulBuild/api/json");
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get CatServers Versions data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: CatServers");
            return null;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type Job.");
        }
        $data = json_decode($data->getBody(), true);
        //Check if artifacts exists and if ['artifacts'][0]['id'] exists
        if (!isset($data['id']) || !$data['id'] || !isset($data['artifacts']) || !$data['artifacts'] || !isset($data['artifacts'][0]) || !isset($data['artifacts'][0]['relativePath']) || !$data['artifacts'][0]['relativePath']) {
            $this->makeError("Can't retrieve $this->type Job.");
        }
        $values = [
            'id' => $data['id'],
            'version' => str_replace('CatServer-', '', $job),
            'url' => "https://jenkins.rbqcloud.cn:30011/job/$job/" . $data['id'] . "/artifact/" . $data['artifacts'][0]['relativePath']
        ];
        return $values;
    }

    #[NoReturn] protected function makeError($message): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        $json = ['success' => false, 'message' => $message];
        $mail = new \App\Mail();
        $mail->send('Error while get CatServers versions', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: CatServers");
        //Die $json
        die(json_encode($json));
    }
}