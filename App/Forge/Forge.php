<?php

namespace App\Forge;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use IvoPetkov\HTML5DOMDocument;
use JetBrains\PhpStorm\NoReturn;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class Forge
{
    protected Client $client;
    protected string $type = 'forge';

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
        $mail->send('Error while get Forge versions', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Forge");

        //Die $json
        die(json_encode($json));
    }

    public function getMcVersions() {
        try {
            $data = $this->client->get('https://files.minecraftforge.net/net/minecraftforge/forge/');
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Forge Versions data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Fabric");
            return;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $html = (string) $data->getBody();

        $dom = new HTML5DOMDocument();
        $dom->loadHTML($html);

        $versions = [];
        foreach ($dom->querySelectorAll('li.li-version-list ul li a') as $a) {
            $href = $a->getAttribute('href');
            if (strpos($href, 'index_') === 0) {
                $versionText = trim($a->textContent);
                if (version_compare($versionText, '1.5.2', '>=')) {

                    if (!in_array($versionText, $versions)) {
                        $versions[] = ['version' => $versionText, 'url' => $href];
                    }
                }
            }
        }
        return $versions;
    }
    public function getVersionData(array $version) {
        try {
            $data = $this->client->get('https://files.minecraftforge.net/net/minecraftforge/forge/' . $version['url']);
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Forge Version data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Fabric");
            return;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type version " . $version['version'] . ".");
        }
        $html = (string) $data->getBody();
        //Open new HTML5 dom for get this div with "title" class
        $dom = new HTML5DOMDocument();
        $dom->loadHTML($html);
        $returnData = [];
        foreach ($dom->querySelectorAll('div.title') as $div) {
            $textContent = trim($div->textContent);
            if(strpos($textContent, 'Latest') !== false) {
                $versionData = $div->querySelector('small');
                if($versionData) {
                    $versionData = trim($versionData->textContent);
                    $versionData = str_replace(' ', '', $versionData);
                    $url = "https://maven.minecraftforge.net/net/minecraftforge/forge/" . $versionData . "/forge-" . $versionData . "-installer.jar";
                    $ver = $version['version'];
                    if (version_compare($ver, "1.10.2", "<") && version_compare($ver, "1.7.2", ">") && !in_array($ver, ['1.7.10_pre4','1.8.8','1.8'])) {
                        if (preg_match('/^\d+\.\d+\.\d+$/', $ver)) {
                            $url = "https://maven.minecraftforge.net/net/minecraftforge/forge/" . $versionData . "-$ver" . "/forge-" . $versionData . "-$ver" . "-installer.jar";
                        }
                        else {
                            $url = "https://maven.minecraftforge.net/net/minecraftforge/forge/" . $versionData . "-$ver" . ".0" . "/forge-" . $versionData . "-$ver" . ".0-installer.jar";
                        }
                    } else if ($ver == '1.7.10_pre4') {
                        $url = "https://maven.minecraftforge.net/net/minecraftforge/forge/" . $versionData . "-prerelease" . "/forge-" . $versionData . "-prerelease-installer.jar";

                    } else if ($ver == '1.7.2') {
                        $url = "https://maven.minecraftforge.net/net/minecraftforge/forge/" . $versionData . "-mc172" . "/forge-" . $versionData . "-mc172-installer.jar";
                    }
                    $returnData = ['version' => $versionData, 'url' => $url];
                } else {
                    $this->makeError("Can't retrieve $this->type version " . $version['version'] . ".");
                }
            }
        }
        //get https://cdn.bagou450.com/versions/minecraft/getlist/Forge.json and decode it
        $json = file_get_contents('https://cdn.bagou450.com/versions/minecraft/getlist/Forge.json');
        $json = json_decode($json, true);
        //Find the element where version is same as $version['version']
        $key = array_search($version['version'], array_column($json, 'version'));
        //If not found, make $version['sha1'] at 000
        if($key === false) {
            $returnData['sha1'] = '000';
        } else {
            //Check if there are a sha1 for this element if not set sha1 at 000
            if(!isset($json[$key]['sha1'])) {
                $returnData['sha1'] = '000';
            } else {
                //Set sha1 at sha1 of this element
                $returnData['sha1'] = $json[$key]['sha1'];
            }
        }
        return $returnData;
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
            //get version (text before first -) and save it in versionShort
            $versionShort = explode('-', $versionData['version'])[0];
            //Check if minecraft/$this->type/$version['id'] exists and don't get same sha1 as $versionData['sha1']
            if (file_exists("minecraft/$this->type/" . $versionShort . '.zip')) {

                //Check minecraft/getlist/Forge.json from files and check if a versionLong == $versionData['version'] exists
                $json = file_get_contents('./minecraft/getlist/Forge.json');
                $exists = in_array($versionData['version'], array_column(json_decode($json, true), 'versionLong'));
                if($exists) {
                    echo "Forge: Same version for " . $versionData['version'] . " ($actualnumber/$number)\n";
                    $versions[] = [
                        'name' => ucfirst($this->type) . " " . $versionShort,
                        'version' => $versionShort,
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
                $mail->send('Error while get Forge Jar data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Fabric");
                return;
            }
            if (!file_exists("minecraft/$this->type/tmp/" . $versionData['version'] . '.jar')) {
                $this->makeError("Can't download Minecraft $this->type version " . $versionData['version'] . ".");
            }

            //Run compiler
            $this->compileVersion($versionData['version']);
            $this->deleteDirectory("minecraft/$this->type/tmp/");

            echo "$this->type: Downloaded " . $versionData['version'] . " ($actualnumber/$number)\n";
            //Make array with name and version with a uppercase first letter of $this->type
            $versions[] = [
                'name' => ucfirst($this->type) . " " . $versionShort,
                'version' => $versionShort,
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

    private function compileVersion($version) {
        //Check version for be sure of what java version use. Versions >=1.18 use java 17 and versions <1.18 use java 8
        $javaVersion = '8';
        if(version_compare($version, '1.18', '>=')) {
            $javaVersion = '17';
        }
        $versionShort = explode('-', $version)[0];

        //Check if java is installed by checking if there are a folder called $javaVersion in ./java/
        if(!is_dir("./java/$javaVersion")) {
            //If not, makeError
            $this->makeError("Can't compile Minecraft $this->type version " . $version . ". Java $javaVersion is not installed.");
        }
        //Check if there are a file called $version.jar in minecraft/$this->type/tmp/
        if(!file_exists("minecraft/$this->type/tmp/" . $version . '.jar')) {
            //If not, makeError
            $this->makeError("Can't compile Minecraft $this->type version " . $version . ". File not found.");
        }
        //If exist run the jar file with java -jar minecraft/$this->type/tmp/$version.jar --installServer in the correct folder
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $cmd = "cd minecraft\\$this->type\\tmp && ..\\..\\..\\java\\$javaVersion-win\\bin\\java.exe -jar $version.jar --installServer";
        } else {
            // Linux (et autres Unix-like)
            $cmd = "cd minecraft/$this->type/tmp && ../../../java/$javaVersion/bin/java -jar $version.jar --installServer";
        }

        exec($cmd, $output, $return_var);
        //Check for errors
        if($return_var !== 0) {
            //If there are errors, makeError
            $this->makeError("Can't compile Minecraft $this->type version " . $version . ". Error: " . implode("\n", $output));
        }
        //Remove installer file and installer.log
        $files = [
            "minecraft/$this->type/tmp/$version.jar",
            "minecraft/$this->type/tmp/$version.log",
            "minecraft/$this->type/tmp/$version.jar.log",
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
        if (file_exists("minecraft/$this->type/tmp/forge-$version-shim.jar")) {
            rename("minecraft/$this->type/tmp/forge-$version-shim.jar", "minecraft/$this->type/tmp/server.jar");
        }
        if (file_exists("minecraft/$this->type/tmp/forge-$version-universal.jar")) {
            rename("minecraft/$this->type/tmp/forge-$version-universal.jar", "minecraft/$this->type/tmp/server.jar");
        }
        if (file_exists("minecraft/$this->type/tmp/libraries/net/minecraftforge/forge/$version/unix_args.txt")) {
            copy("minecraft/$this->type/tmp/libraries/net/minecraftforge/forge/$version/unix_args.txt", "minecraft/$this->type/tmp/unix_args.txt");
        }
        //Compress the content of minecraft/$this->type/tmp/ in minecraft/$this->type/$versionShort.zip
        $zip = new \ZipArchive();
        $zipPath = "./minecraft/$this->type/$versionShort.zip";
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