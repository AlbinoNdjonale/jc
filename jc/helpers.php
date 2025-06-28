<?php

    use jc\qbuilder\QBuilder;
    use jc\Jc;

    define("DB_TEST", "tests/test.db");

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
            getenv('TEST')=='true'?"sqlite":getenv('DATABASE'),
            getenv('TEST')=='true'?__DIR__.'/../'.DB_TEST:getenv('DATABASENAME'),
            getenv('DATABASEPASSWORD'),
            getenv('DATABASEHOST'),
            getenv('DATABASEUSER'),
            (int) getenv('DATABASEPORT')
        );
    }

    function __send_file_log(string $content) {
        $file_log = __DIR__.'/storage/log/jc.log';

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

    function up_env() {
        if (file_exists('.env'))
        foreach (file('.env') as $line) {
            if (empty(trim($line)) || str_starts_with($line, '#')) continue;
            
            $split = explode('=', $line, 2);

            if (count($split) == 2 and !getenv($split[0]))
                putenv(trim($split[0]).'='.trim($split[1]));
        }
    }
    