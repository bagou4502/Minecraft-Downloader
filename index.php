<?php

use App\Main;

require './vendor/autoload.php';


$vanilla = new Main();
echo print_r($vanilla->downloadAll());