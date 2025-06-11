<?php

require_once '../vendor/autoload.php';

use Joomla\Http\HttpFactory;

echo "Trying static call: HttpFactory::getHttp([])\n";
try {
    $http = HttpFactory::getHttp([]);
    echo "Static call worked!\n";
} catch (Error $e) {
    echo "Static call failed: " . $e->getMessage() . "\n";
}

echo "\n";

echo "Trying instance call: (new HttpFactory())->getHttp([])\n";
try {
    $factory = new HttpFactory();
    $http = $factory->getHttp([]);
    echo "Instance call worked!\n";
} catch (Error $e) {
    echo "Instance call failed: " . $e->getMessage() . "\n";
}
