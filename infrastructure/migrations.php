<?php

    require __DIR__."/../jc/migration.php";

    use jc\qbuilder\QBuilder;
                        
    $migrations = [
        "create user" => [
            fn(QBuilder $db) => $db->table("user")->create([...SCHEMA_USER])
        ],

        "Configure Token" => [
            fn(QBuilder $db) => $db->table("token")->create([...SCHEMA_TOKEN]),

            fn(QBuilder $db) => $db->table("token_restart")->create([...SCHEMA_TOKEN_RESTART])
        ],

        "Create table publich" => [
            fn(QBuilder $db) => $db->table("publich")->create([
                "id"      => [integer_(), primary_key(), not_null(), auto_increment()],
                "content" => [text(), not_null()],
                "user"    => [integer_(), foreign_key(), not_null(), "reference" => "user(id)", "on_delete" => "CASCADE"]
            ])
        ],

        "Rename table publich => post" => [
            fn(QBuilder $db) => $db->table('publich')->rename('post')
        ],

        "Add column date to table post" => [
            fn(QBuilder $db) => $db->table('post')->add_column('date', [date_(), not_null(), "default" => "CURRENT_TIMESTAMP"])
        ],

        "Change column date => created_at to table post" => [
            fn(QBuilder $db) => $db->table('post')->change_column('date', [date_(), not_null(), "default" => "CURRENT_TIMESTAMP"], 'created_at')
        ],

        "Drop column created_at from table post" => [
            fn(QBuilder $db) => $db->table('post')->drop_column('created_at')
        ],

        "Drop table post" => [
            fn(QBuilder $db) => $db->table("post")->drop()
        ]
    ];

    run_migration($migrations);
