<?php
    namespace jc\util;

    use jc\qbuilder\QBuilder;
    use DateTime;

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

        public static function create_user($params) {
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

        public static function set_user($user) {
            self::$user = $user;
        }

        public static function get_user() {
            return self::$user;
        }

        public static function is_valid($values, $cond) {
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

        public static function authenticated($iduser, $validat = 2) {
            if (!$validat && Util::get_user()) return null;
            
            $db = db();

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

                $db->close();

                return [
                    'token' => $token['content'],
                    'token_restart' => $tokenrestart
                ];
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