<?php
    $descriptor_spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $processs = [];

    foreach (glob("{$argv[1]}/func_*.php") as $func) {
        $process = proc_open("php $func", $descriptor_spec, $pipes);
        
        array_push($processs, [
            "process" => $process,
            "pipes"   => $pipes,
            "name"    => $func
        ]);
    }

    echo count($processs)." process running".PHP_EOL;

    try {
        while (true) {
            foreach ($processs as $process) {
                $status = proc_get_status($process["process"]);

                if (!$status["running"]) {
                    echo "process {$process["name"]} exited with code {$status["exitcode"]}".PHP_EOL;
                }
            }
        }
    } catch (\Throwable $th) {
        echo "Terminando processos em segundo plano";

        foreach ($processs as $process) {
            fclose($process["pipes"][0]);
            fclose($process["pipes"][1]);
            fclose($process["pipes"][2]);

            proc_close($process["process"]);
        }
    }
    