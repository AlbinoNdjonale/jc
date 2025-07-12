<?php

    $connection = $argv[1];
    $data       = $argv[2];

    do {
        $hundle = fopen($connection, 'a');
    } while (!$hundle);

    flock($hundle, LOCK_EX);

    fwrite($hundle, $data.PHP_EOL);

    flock($hundle, LOCK_UN);

    fclose($hundle);