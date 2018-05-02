<?php

include __DIR__."/../vendor/autoload.php";

$client = new \lanzhi\redis\Client('localhost', 6379);

$start = microtime(true);
$total = 10;
for($i=0; $i<$total; $i++){
    $key = uniqid();
    $cmd = $client->string()->set($key, $i, 60);
    $cmd->run();

    for($j=0; $j<10000; $j++){
        $cmd = $client->string()->incr($key);
        $cmd->run();
    }
}

$end = microtime(true);

echo "time usage:", $end-$start, "\n";
