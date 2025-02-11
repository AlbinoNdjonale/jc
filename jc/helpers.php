<?php

    use jc\qbuilder\QBuilder;

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