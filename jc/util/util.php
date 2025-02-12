<?php
    namespace jc\util;

    use DateTime;
    use jc\qbuilder\QBuilder;
    use jc\response\JSONResponse;
    use jc\response\RedirectResponse;
    use jc\response\Render;

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

    class Util {
        protected static $user = null;

        public static function create_user(array $params) {
            $is_valid = self::is_valid($params, [
                'username' => 'string|required',
                'email' => 'is_email|required',
                'password' => 'string|required|minlength-8',
                'is_admin' => 'int|required',
                'is_active' => 'int',
                'first_name' => 'string',
                'last_name' => 'string',
                'gender' => 'enum(M, F)',
                'birth' => 'string'
            ]);

            if ($is_valid[0]) {
                $db = db();

                $params['password'] = hash('sha256', $params['password'].getenv('SECRETKEY'));

                $db->table('user')->insert($params)->execute();

                $user = $db->table('user')
                   ->select()
                   ->where('email = '.QBuilder::prepare($params['email']))
                   ->and_where('password = '.QBuilder::prepare($params['password']))
                   ->query()
                   ->first();

                $db->close();

                return [true, $user];
            }
            
            return $is_valid;
        }

        public static function set_user(array $user) {
            self::$user = $user;
        }

        public static function get_user() {
            return self::$user;
        }

        public static function is_valid(array $values, array $cond) {
            $messages = [];

            foreach ($cond as $key => $value) {
                $messages[$key] = [];

                $terms = explode('|', $value);

                if (in_array('required', $terms) && !isset($values[$key])) array_push($messages[$key], 'this field is required');
                if (isset($values[$key])) {
                    if (in_array('string', $terms) && !is_string($values[$key])) array_push($messages[$key], 'this field must be \'string\'');
                    if (in_array('int', $terms) && !is_int($values[$key])) array_push($messages[$key], 'this field must be \'integer\'');
                    if (in_array('float', $terms) && !is_float($values[$key])) array_push($messages[$key], 'this field must be \'float\'');
                    if (in_array('bool', $terms) && !is_bool($values[$key])) array_push($messages[$key], 'this field must be \'boolean\'');
                    if (preg_match('/enum-[(](.*?)[)]/', $value, $matches) && !in_array((string) $values[$key], explode(',', $matches[1]))) array_push($messages[$key], 'this field must be in \''.$matches[1].'\'');

                    if (is_string($values[$key])) {
                        if (!in_array('blank', $terms) && trim($values[$key]) == '') array_push($messages[$key], 'this field can\'t be \'blanck\'');
                        if (in_array('is_email', $terms) && !(preg_match("/^[a-z0-9]+@[a-z]+\.[a-z]+[a-z\.]*[^\.]$/", $values[$key]))) array_push($messages[$key], 'this field must be \'email\'');
                        if (preg_match("/[|]length-(\d+)[|]/", "|$value|", $matches) && ((int) $matches[1]) != strlen($values[$key])) array_push($messages[$key], 'field\'s length must be equal to \''.$matches[1].'\'');
                        if (preg_match("/[|]maxlength-(\d+)[|]/", "|$value|", $matches) && !(((int) $matches[1]) >= strlen($values[$key]))) array_push($messages[$key], 'field\'s length must be equal to or less to \''.$matches[1].'\'');
                        if (preg_match("/[|]minlength-(\d+)[|]/", "|$value|", $matches) && !(((int) $matches[1]) <= strlen($values[$key]))) array_push($messages[$key], 'field\'s length must be equal to or larger to \''.$matches[1].'\'');
                    }
                }

                if (count($messages[$key]) == 0) unset($messages[$key]);
            }

            return [count($messages) == 0, $messages];
        }

        public static function login(array $data, $validat, $templa_tename = '') {
            $is_valid = Util::is_valid(
                $data,
                [
                    'password' => 'required|string|minlength-8',
                    'email_or_username' => 'required|string'
                ]
            );

            if (!$is_valid[0]) {
                if (!$templa_tename) return new JSONResponse($is_valid[1], 400);

                return new Render($templa_tename, [
                    'error' => $is_valid[1]
                ], 400);
            }   
            
            $db = db();

            $email_or_username = $data['email_or_username'];
            $password = $data['password'];

            $re_email = "/^[a-z0-9]+@[a-z]+\.[a-z]+[a-z\.]*[^\.]$/";

            $attr = match ((bool) preg_match($re_email, $email_or_username)) {
                true  => 'email',
                false => 'username',
            };

            $user = $db->table('user')
                ->select()
                ->where('password = \''.hash('sha256', $password.getenv('SECRETKEY')).'\'')
                ->and_where("$attr = ".QBuilder::prepare($email_or_username))
                ->query()
                ->first();

            $db->close();

            if (!$user) {
                if (!$templa_tename) return new JSONResponse([
                    'detail' => 'credential invalids'
                ], 403);

                return new Render($templa_tename, [
                    'error' => 'credential invalids'
                ], 403);
            }
                    
            $auth = Util::authenticated($user['id'], !$templa_tename, $validat);

            if (!$templa_tename)
                return new JSONResponse($auth);

            $next = $data['_next'];
            return new RedirectResponse($next);
        }

        public static function authenticated(string|int $iduser, bool $api = true, int|bool $validat = 2) {
            $db = db();
            
            if (Util::get_user()) {
                if (!$api && Util::get_user()['id'] === $iduser) {
                    $db->close();

                    return null;
                } else if ($api && Util::get_user()['id'] === $iduser) {
                    $token = $db->table('token')->select()->where("content = '".$_COOKIE['token']."'")->query()->first();
                    $tokenrestart = $db->table('token_restart')->select()->where('token = '.$token['id'])->query()->first();
                    
                    $db->close();

                    return [
                        'token' => $token['content'],
                        'token_restart' => $tokenrestart['content']
                    ];
                } else {
                    $token = $_COOKIE['token'];

                    $db->table('token')->delete()->where('content = '.QBuilder::prepare($token))->execute();

                    $db->table('user')->update([
                        'is_active' => 0
                    ])->where('id = '.QBuilder::prepare(Util::get_user()['id']))->execute();
                }
            } 

            $date = new DateTime();

            $token = hash('sha256', self::randomword().getenv('SECRETKEY')); 

            $attrs = [
                'user' => $iduser,
                'content' => $token,
                'csrf' => 0
            ];

            if ($validat) $attrs['valid_until'] = $date->modify("+$validat hour")->format('Y-m-d H:i:s');

            $db->table('token')->insert($attrs)->execute();

            $token = $db->table('token')->select(['id', 'content'])->where("content = '$token'")->query()->first();
            
            $db->table('user')->update([
                'is_active' => 1
            ])->where('id = '.QBuilder::prepare($iduser))->execute();

            if ($validat) {
                $tokenrestart = hash('sha256', self::randomword().getenv('SECRETKEY'));

                $db->table('token_restart')->insert([
                    'token' => $token['id'],
                    'content' => $tokenrestart,
                    'valid_until' => $date->modify("+96 hour")->format('Y-m-d H:i:s')
                ])->execute();
            }
            
            if ($api) {
                $response = [
                    'token' => $token['content']
                ];

                if ($validat)
                    $response['token_restart'] = $tokenrestart;

                $db->close();

                return $response;
            }

            setcookie('token', $token['content'], 0, '/');
            $db->close();

            return null;

        }

        public static function randomword() {
            return implode('', array_map(function() {
                return [
                    'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i',
                    '1', '2', '3', '4', '5', '6', '7', '8', '9',
                    '@', '#', '%', '?', '&', '*', '|', '!', '$'
                ][random_int(0, 26)];
            }, range(0, 20)));
        }
    }