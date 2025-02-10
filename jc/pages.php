<?php
    namespace jc\pages;

    use DateTime;
    use jc\response\JSONResponse;
    use jc\response\Render;
    use jc\response\RedirectResponse;
    use jc\qbuilder\QBuilder;
    use jc\util\Util;

    function db() {
        return new QBuilder(
            getenv('DATABASE'),
            getenv('DATABASENAME'),
            getenv('DATABASEPASSWORD'),
            getenv('DATABASEHOST'),
            getenv('DATABASEUSER'),
            (int) getenv('DATABASEPORT')
        );
    }

    class Pages {
        public static function login($templa_tename, $validat = false) {
            return function($request) use ($templa_tename, $validat) {
                if ($request['METHOD'] == 'GET')
                    return new Render($templa_tename);

                
                return Util::login($request['POST'], $validat, $templa_tename);
            };
        }

        public static function logout($next) {
            return function($request) use ($next) {
                $user = Util::get_user();

                if ($user) {
                    $db = db();

                    $token = $request['COOKIE']['token'];

                    $db->table('token')->delete()->where('content = '.QBuilder::prepare($token))->execute();

                    $db->table('user')->update([
                        'is_active' => 0
                    ])->where('id = \''.$user['id'].'\'')->execute();

                    setcookie('token', '', 1, '/');
                
                    $db->close();
                }

                return new RedirectResponse($next);
            };
        }

        public static function token($validat = 2) {
            return function ($request) use ($validat) {
                switch ($request['METHOD']) {
                    case 'POST':
                        return Util::login($request['POST'], $validat);
                    
                    case 'DELETE':
                        $user = Util::get_user();

                        if ($user) {
                            $db = db();

                            $token = $request['HEADERS']['authorization'];

                            $db->table('token')->delete()->where('content = '.QBuilder::prepare($token))->execute();

                            $db->table('user')->update([
                                'is_active' => 0
                            ])->where('id = \''.$user['id'].'\'')->execute();
                        }

                        $db->close();

                        return new JSONResponse([
                            'detail' => 'logout whit sucess'
                        ]);
                }
            };
        }

        public static function restart_token($validat = 2) {
            return function($request) use ($validat) {
                $token_restart = $request['GET']['token_restart'];

                $db = db();

                $token_restart = $db->table('token_restart')
                    ->select()
                    ->where('content = '.QBuilder::prepare($token_restart))
                    ->query()
                    ->first();

                if (!$token_restart) {
                    $db->close();

                    return new JSONResponse([
                        'detail' => 'restart token not found'
                    ], 404);
                }

                if ((new DateTime()) >= (new DateTime($token_restart['valid_until']))) {
                    $db->table('token')
                        ->delete()
                        ->where('id = '.$token_restart['token'])
                        ->execute();
                    
                    $db->close();

                    return new JSONResponse([
                        'detail' => 'token restart expired'
                    ], 401);
                }

                $date = new DateTime();

                $db->table('token')
                    ->update(['valid_until' => $date->modify("+$validat hour")->format('Y-m-d H:i:s')])
                    ->where('id = '.$token_restart['token'])
                    ->execute();

                $db->close();

                return new JSONResponse([
                    'detail' => 'token restarted with sucess'
                ]);
            };
        }
    }