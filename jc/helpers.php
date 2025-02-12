<?php

    use jc\qbuilder\QBuilder;
    use jc\Jc;

    function dbg(): array {
        return debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
    }

    function dump($value): never {
        echo '<pre>';
        print_r($value);
        echo '</pre>';
        
        exit(0);
    }

    function db(): QBuilder {
        return new QBuilder(
            getenv('DATABASE'),
            getenv('DATABASENAME'),
            getenv('DATABASEPASSWORD'),
            getenv('DATABASEHOST'),
            getenv('DATABASEUSER'),
            (int) getenv('DATABASEPORT')
        );
    }

    function __send_file_log(string $content) {
        $file_log = __DIR__.'/log/jc.log';

        $hundle = match (file_exists($file_log)) {
            true => fopen($file_log, 'a'),
            false => fopen($file_log, 'w'),
        };

        if ($hundle) {
            fwrite($hundle, "$content\n");

            fclose($hundle);
        }
    }

    function send_file_log(string $content) {
        Jc::add_log($content);
    }