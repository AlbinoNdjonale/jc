<?php

    require __DIR__."/../jc/qbuilder/qbuilder.php";
    require __DIR__."/../jc/helpers.php";

    up_env();

    function integer_(): string {
        return "INTEGER";
    }

    function boolean_(): string {
        return "BOOLEAN";
    }

    function varchar(int $max): string {
        return "VARCHAR($max)";
    }

    function text(): string {
        return "TEXT";
    }

    function enum(string $op1, string $op2): string {
        return "ENUM('$op1', '$op2')";
    }

    function date_(): string {
        return "DATE";
    }

    function datetime(): string {
        return "DATETIME";
    }

    function not_null(): string {
        return "not_null";
    }

    function foreign_key(): string {
        return "foreign_key";
    }

    function primary_key(): string {
        return "primary_key";
    }

    function auto_increment(): string {
        return "auto_increment";
    }

    function unique(): string {
        return "unique";
    }

    define('SCHEMA_USER', [
        "id"          => [integer_(), primary_key(), not_null(), auto_increment()],
        "username"    => [varchar(50), not_null()],
        "first_name"  => [varchar(50)],
        "last_name"   => [varchar(50)],
        "date_joined" => [datetime(), not_null(), "default" => "CURRENT_TIMESTAMP"],
        "last_login"  => [datetime()],
        "is_active"   => [boolean_(), not_null(), "default" => 0],
        "is_admin"    => [boolean_(), not_null()],
        "gender"      => [varchar(1)],
        "birth"       => [date_()],
        "email"       => [varchar(250), not_null(), unique()],
        "password"    => [varchar(66), not_null()]
    ]);

    define('SCHEMA_TOKEN', [
        "id"          => [integer_(), not_null(), primary_key(), auto_increment()],
        "content"     => [varchar(66), not_null(), unique()],
        "valid_until" => [datetime()],
        "csrf"        => [boolean_(), not_null()],
        "user"        => [integer_(), foreign_key(), "reference" => "user(id)", "on_delete" => "CASCADE"]
    ]);

    define("SCHEMA_TOKEN_RESTART", [
        "id"          => [integer_(), not_null(), primary_key(), auto_increment()],
        "content"     => [varchar(66), not_null(), unique()],
        "valid_until" => [datetime(), not_null()],
        "token"       => [integer_(), foreign_key(), not_null(), "reference" => "token(id)", "on_delete" => "CASCADE"]
    ]);
    
    function run_migration(array $migrations) {
        $db    = db();
        $error = 0;
        $migrations_executed = 0;

        $db->table('__migrations')->create([
            "id"         => [integer_(), primary_key(), not_null(), auto_increment()],
            "name"       => [varchar(150), not_null(), unique()],
            "created_at" => [datetime(), not_null(), "default" => "CURRENT_TIMESTAMP"]
        ])->execute();

        $last_migration = $db->table('__migrations')
            ->select()
            ->order_by('id')
            ->desc()
            ->limit(1)
            ->query()
            ->first();
        
        $detected_news_migration = $last_migration === null;

        foreach ($migrations as $migration_name => $migration) {
            if (!$detected_news_migration) {
                $detected_news_migration = $last_migration['name'] === $migration_name;
                continue;
            }

            foreach ($migration as $func) {
                $func($db);
                $db->save_sql();
            }

            if ($db->execute_all()) {
                $migrations_executed++;
                echo "Migration '$migration_name' executed\n";
                $db->table("__migrations")->insert(['name' => $migration_name])->execute();
            } else {
                $error = 1;
                break;
            }
        }

        echo $migrations_executed>0?"\n":"";

        $s = $migrations_executed>1?"s":"";
        echo "Run $migrations_executed migration$s\n";

        $db->close();
        exit($error);
    }