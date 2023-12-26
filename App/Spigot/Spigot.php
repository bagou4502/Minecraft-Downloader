<?php

namespace App\Spigot;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use IvoPetkov\HTML5DOMDocument;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Component\Filesystem\Filesystem;

class Spigot
{
    protected Client $client;
    protected string $type = 'spigot';

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
        $mail->send('Error while get Spigot versions', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Spigot");

        //Die $json
        die(json_encode($json));
    }

    public function getMcVersions() {
        try {
            $data = $this->client->get('https://www.spigotmc.org/wiki/buildtools/#running-buildtools');
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Spigot Versions data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Spigot");
            return;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type Versions.");
        }
        $html = (string) $data->getBody();

        $dom = new HTML5DOMDocument();
        try {
            $dom->loadHTML($html, \IvoPetkov\HTML5DOMDocument::ALLOW_DUPLICATE_IDS);
        } catch (\Throwable $th) {
            $mail = new \App\Mail();
            $message = $th->getMessage();
            $mail->send('Error while get Spigot HTML data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Spigot");
            return null;
        }

        $versions = [];
        foreach ($dom->querySelectorAll('li.col3 a') as $a) {
            $versionText = trim($a->textContent);
            if (str_starts_with($versionText, '1.')) {
                $versions[] = ['version' => $versionText, 'url' => "https://hub.spigotmc.org/versions/$versionText.json"];
            }
        }
        return $versions;
    }
    public function getVersionData(array $version) {
        try {
            $data = $this->client->get($version['url']);
        } catch (GuzzleException $e) {
            $mail = new \App\Mail();
            $message = $e->getMessage();
            $mail->send('Error while get Spigot Version data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Spigot");
            return null;
        }
        if ($data->getStatusCode() !== 200) {
            $this->makeError("Can't retrieve $this->type version " . $version['version'] . ".");
        }
        $json = json_decode($data->getBody(), true);
        $returnData = [
            'version' => $version['version'],
            'build' => $json['name']
        ];

        return $returnData;
    }

    public function downloadVersions(): void
    {
        $versions = [];
        $data = $this->getMcVersions();
        $number = count($data);
        $actualnumber = 0;
        $this->deleteDirectory("minecraft/$this->type/tmp/");
        foreach ($data as $version) {
            $actualnumber++;
            $versionData = $this->getVersionData($version);
            $versionName = $versionData['version'];
            $build = $versionData['build'];
            if (file_exists("minecraft/$this->type/" . $versionName)) {
                //Check minecraft/getlist/Spigot.json from files and check if a versionLong == $versionData['version'] exists
                $json = file_get_contents('./minecraft/getlist/Spigot.json');
                $exists = in_array($build, array_column(json_decode($json, true), 'build'));
                if($exists) {
                    echo "Spigot: Same version for " . $versionData['version'] . " ($actualnumber/$number)\n";
                    $versions[] = [
                        'name' => ucfirst($this->type) . " " . $versionName,
                        'version' => $versionName,
                        'build' => $build
                    ];
                    continue;
                };

            }
            //Create a folder called tmp in minecraft/$this->type/ if not exists
            if (!is_dir("minecraft/$this->type/tmp")) {
                mkdir("minecraft/$this->type/tmp", 0777, true);
            }
            //Download buildTools and save it in minecraft/$this->type/tmp/buildtools.jar use Guzzle
            try {
                $this->client->get('https://hub.spigotmc.org/jenkins/job/BuildTools/lastSuccessfulBuild/artifact/target/BuildTools.jar', ['sink' => "minecraft/$this->type/tmp/buildtools.jar"]);
            } catch (GuzzleException $e) {
                $mail = new \App\Mail();
                $message = $e->getMessage();
                $mail->send('Error while get Spigot Jar data', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Spigot");
                return;
            }
            if (!file_exists("minecraft/$this->type/tmp/buildtools.jar")) {
                //If not, makeError
                $this->makeError("Can't download BuildTools?!?!");
            }

            //Run compiler
            $this->compileVersion($versionName, $build);

            echo "$this->type: Downloaded " . $versionName . " ($actualnumber/$number)\n";
            //Make array with name and version with a uppercase first letter of $this->type
            $versions[] = [
                'name' => ucfirst($this->type) . " " . $versionName,
                'version' => $versionName,
                'build' => $build
            ];
        }
        $this->generateJson($versions, $this->type);
        $this->deleteDirectory("minecraft/$this->type/tmp/");


    }
    private function deleteDirectory($dir) {
        try {
            $fs = new Filesystem();
            $fs->remove($dir);
        } catch (\Throwable $th) {
            $mail = new \App\Mail();
            $message = $th->getMessage();
            $mail->send('Error while delete Spigot tmp folder', "Hello<br/> Ya une petite erreur .<br/> Error: $message <br/><br/> Type: Spigot");
            return;
        }

    }

    private function compileVersion($versionName, $build) {
        //Check version for be sure of what java version use. Versions >=1.18 use java 17 and versions <1.18 use java 8
        $javaVersion = '8';
        if(version_compare($versionName, '1.18', '>=')) {
            $javaVersion = '17';
        } else if(version_compare($versionName, '1.17', '>=')) {
            $javaVersion = '16';
        }

        //Check if java is installed by checking if there are a folder called $javaVersion in ./java/
        if(!is_dir("./java/$javaVersion")) {
            //If not, makeError
            $this->makeError("Can't compile Minecraft $this->type version " . $versionName . ". Java $javaVersion is not installed.");
        }
        //Check if there are a file called buildtools.jar in minecraft/$this->type/tmp/
        if(!file_exists("minecraft/$this->type/tmp/buildtools.jar")) {
            //If not, makeError
            $this->makeError("Can't compile Minecraft $this->type version " . $versionName . ". BuildTools.jar is not downloaded.");
        }
        //If exist run the jar file with java -jar minecraft/$this->type/tmp/$version.jar --installServer in the correct folder
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $cmd = "cd minecraft\\$this->type\\tmp && ..\\..\\..\\java\\$javaVersion-win\\bin\\java.exe -jar buildtools.jar --rev $versionName";
            $cmd .= " > NUL 2>&1";
        } else {
            // Linux (et autres Unix-like)
            $cmd = "cd minecraft/$this->type/tmp && ../../../java/$javaVersion/bin/java -jar buildtools.jar --rev $versionName";
            $cmd .= " 2>/dev/null";
        }

        exec($cmd, $output, $return_var);
        //Check for errors
        if($return_var !== 0) {
            //If there are errors, makeError
            $this->makeError("Can't compile Minecraft $this->type version " . $versionName . ". Error: " . implode("\n", $output));
        }

        //Rename server jar to version.jar
        if (file_exists("minecraft/$this->type/tmp/spigot-$versionName.jar")) {
            rename("minecraft/$this->type/tmp/spigot-$versionName.jar", "minecraft/$this->type/$versionName");
        }


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