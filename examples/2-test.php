<?php

include __DIR__."/../vendor/autoload.php";

$scheduler = \lanzhi\coroutine\Scheduler::getInstance();


$client = new \lanzhi\redis\Client('localhost', 6379);

$start = microtime(true);
$total = 10;
for($i=0; $i<$total; $i++){

    $generator = function ($i) use ($client){
        $key = uniqid();
        $cmd = $client->string()->set($key, $i, 60);
        yield from $cmd();

        for($j=0; $j<10000; $j++){
            $cmd = $client->string()->incr($key);
            yield from $cmd();
        }
    };

    $scheduler->register(\lanzhi\coroutine\Scheduler::buildRoutine($generator($i)));
}
$scheduler->run();
$end = microtime(true);

echo "time usage:", $end-$start, "\n";
