<?php

namespace App\Forge;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use IvoPetkov\HTML5DOMDocument;
use JetBrains\PhpStorm\NoReturn;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class NeoForge
{
    protected Client $client;
    protected string $type = 'neoforge';

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
        $mail->send('Error while get Neoforge versions', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: NeoForge");
        //Die $json
        die(json_encode($json));
    }

    public function getMcVersions() {
        try {
            $data = $this->client->get('https://maven.neoforged.net/api/maven/versions/releases/net/neoforged/neoforge');
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get NeoForge Versions data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Fabric");
            return;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $data = json_decode($data->getBody(), true);


        $versions = [];
        foreach ($data['versions'] as $version) {
            preg_match('/^(\d+\.\d+)\./', $version, $matches);
            $majorMinor = $matches[1];

            if (!isset($versions[$majorMinor]) || version_compare($version, $versions[$majorMinor], '>')) {
                $versions[$majorMinor] = $version;
            }
        }
        return array_reverse(array_values($versions));
    }
    public function getVersionData(string $version) {
       return [
           'url' => "https://maven.neoforged.net/releases/net/neoforged/neoforge/$version/neoforge-$version-installer.jar",
           'version' => $version,
           'mcversion' => '1.' . implode('.', array_slice(explode('.', $version), 0, 2))
       ];
    }


    public function downloadVersions(): void
    {
        $versions = [];
        $data = $this->getMcVersions();
        $number = count($data);
        $actualnumber = 0;
        foreach ($data as $version) {
            $actualnumber++;
            $this->deleteDirectory("minecraft/$this->type/tmp/");
            $versionData = $this->getVersionData($version);
            //Check if minecraft/$this->type/$version['id'] exists and don't get same sha1 as $versionData['sha1']
            if (file_exists("minecraft/$this->type/" . $versionData['mcversion'] . '.zip')) {

                //Check minecraft/getlist/Forge.json from files and check if a versionLong == $versionData['version'] exists
                $json = file_get_contents('./minecraft/getlist/Neoforge.json');
                $exists = in_array($versionData['version'], array_column(json_decode($json, true), 'versionLong'));
                if($exists) {
                    echo "NeoForge: Same version for 1." . $versionData['version'] . " ($actualnumber/$number)\n";
                    $versions[] = [
                        'name' => ucfirst($this->type) . " " . $versionData['mcversion'],
                        'version' => $versionData['mcversion'],
                        'versionLong' => $versionData['version']
                    ];
                    continue;
                };

            }
            //Create a folder called tmp in minecraft/$this->type/ if not exists
            if (!is_dir("minecraft/$this->type/tmp")) {
                mkdir("minecraft/$this->type/tmp", 0777, true);
            }
            //Download $versionData['url'] and save it in minecraft/$this->type/tmp/$version['id'].zip use Guzzle
            try {
                $this->client->get($versionData['url'], ['sink' => "minecraft/$this->type/tmp/" . $versionData['version'] . '.jar']);
            } catch (GuzzleException $e) {
                $mail = new \App\Mail();
                $message = $e->getMessage();
                $mail->send('Error while get NeoForge Jar data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Fabric");
                return;
            }
            if (!file_exists("minecraft/$this->type/tmp/" . $versionData['version'] . '.jar')) {
                $this->makeError("Can't download Minecraft $this->type version " . $versionData['version'] . ".");
            }

            //Run compiler
            $this->compileVersion($versionData);
            $this->deleteDirectory("minecraft/$this->type/tmp/");

            echo "$this->type: Downloaded 1." . $versionData['version'] . " ($actualnumber/$number)\n";
            //Make array with name and version with a uppercase first letter of $this->type
            $versions[] = [
                'name' => ucfirst($this->type) . " " . $versionData['mcversion'],
                'version' => $versionData['mcversion'],
                'versionLong' => $versionData['version']
            ];
        }
        $this->generateJson($versions, $this->type);

    }
    private function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $fullPath = "$dir/$file";
                if (is_dir($fullPath)) {
                    $this->deleteDirectory($fullPath);
                } else {
                    unlink($fullPath);
                }
            }
        }
        rmdir($dir);
    }

    private function compileVersion(array $version) {
        //Check version for be sure of what java version use. Versions >=1.18 use java 17 and versions <1.18 use java 8
        $javaVersion = '17';

        //Check if java is installed by checking if there are a folder called $javaVersion in ./java/
        if(!is_dir("./java/$javaVersion")) {
            //If not, makeError
            $this->makeError("Can't compile Minecraft $this->type version " . $version['version'] . ". Java $javaVersion is not installed.");
        }
        //Check if there are a file called $version.jar in minecraft/$this->type/tmp/
        if(!file_exists("minecraft/$this->type/tmp/" . $version['version'] . '.jar')) {
            //If not, makeError
            $this->makeError("Can't compile Minecraft $this->type version " . $version['version'] . ". File not found.");
        }
        //If exist run the jar file with java -jar minecraft/$this->type/tmp/$version.jar --installServer in the correct folder
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $cmd = "cd minecraft\\$this->type\\tmp && ..\\..\\..\\java\\$javaVersion-win\\bin\\java.exe -jar " .  $version['version'] . ".jar --installServer";
        } else {
            // Linux (et autres Unix-like)
            $cmd = "cd minecraft/$this->type/tmp && ../../../java/$javaVersion/bin/java -jar " . $version['version'] . ".jar --installServer";
        }

        exec($cmd, $output, $return_var);
        //Check for errors
        if($return_var !== 0) {
            //If there are errors, makeError
            $this->makeError("Can't compile Minecraft $this->type version 1." . $version['version'] . ". Error: " . implode("\n", $output));
        }
        //Remove installer file and installer.log
        $files = [
            "minecraft/$this->type/tmp/" . $version['version'] . ".jar",
            "minecraft/$this->type/tmp/" . $version['version'] . ".log",
            "minecraft/$this->type/tmp/" . $version['version'] . ".jar.log",
            "minecraft/$this->type/tmp/installer.log",
            "minecraft/$this->type/tmp/run.bat",
            "minecraft/$this->type/tmp/run.sh"
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        //Rename server jar to server.jar
        if (file_exists("minecraft/$this->type/tmp/neoforge-" . $version['version'] . "-shim.jar")) {
            rename("minecraft/$this->type/tmp/neoforge-" . $version['version'] . "-shim.jar", "minecraft/$this->type/tmp/server.jar");
        }
        if (file_exists("minecraft/$this->type/tmp/neoforge-" . $version['version'] . "-universal.jar")) {
            rename("minecraft/$this->type/tmp/neoforge-" . $version['version'] . "-universal.jar", "minecraft/$this->type/tmp/server.jar");
        }
        if (file_exists("minecraft/$this->type/tmp/libraries/net/neoforged/neoforge/" . $version['version'] . "/unix_args.txt")) {
            copy("minecraft/$this->type/tmp/libraries/net/neoforged/neoforge/" . $version['version'] . "/unix_args.txt", "minecraft/$this->type/tmp/unix_args.txt");
        }
        //Compress the content of minecraft/$this->type/tmp/ in minecraft/$this->type/$versionShort.zip
        $zip = new \ZipArchive();
        $zipPath = "./minecraft/$this->type/". $version['mcversion'] .".zip";
        $dirPath = "./minecraft/$this->type/tmp/";

        $this->zipperDossier($dirPath,$zipPath);
    }
    protected function zipperDossier($dossierSource, $zipDestination) {
        //Delete file if already exists
        if (file_exists($zipDestination)) {
            unlink($zipDestination);
        }
        $zip = new ZipArchive();

        if ($zip->open($zipDestination, ZipArchive::CREATE) !== TRUE) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dossierSource));
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($dossierSource) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return true;
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