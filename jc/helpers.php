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

    function db(bool $nocqrs = false, bool $write = false): QBuilder {
        return new QBuilder(
            getenv('TEST')=='true'?"sqlite":getenv('DATABASE'),
            getenv('TEST')=='true'?__DIR__.'/../'.DB_TEST:(getenv('DATABASENAME').($write?"_write":"")),
            getenv('DATABASEPASSWORD'),
            getenv('DATABASEHOST'),
            getenv('DATABASEUSER'),
            (int) getenv('DATABASEPORT'),
            $nocqrs?false:(getenv('CQRS') == 'true')
        );
    }

    function hash_(string $data) {
        return hash('sha256', $data.getenv('SECRETKEY'));
    }

    function __send_file_log(string $content) {
        $file_log = __DIR__.'/storage/log/jc.log';

        if (file_exists($file_log)) {
            $hundle = fopen($file_log, 'a');
        } else {
            $hundle = fopen($file_log, 'w');
            chmod($file_log, 0666);
        }

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
    