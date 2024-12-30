<?php
    namespace jc\pages;

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
        public static function login($templa_tename) {
            return function($request) use ($templa_tename) {
                if ($request['METHOD'] == 'GET')
                    return new Render($templa_tename);

                $is_valid = Util::is_valid(
                    $request['POST'],
                        
                    [
                        'password' => 'required|string|minlength-8',
                        'email_or_username' => 'required|string'
                    ]
                );

                if (!$is_valid[0])
                    return new Render($templa_tename, [
                        'error' => $is_valid[1]
                    ], 400);
                
                $db = db();

                $email_or_username = $request['POST']['email_or_username'];
                $password = $request['POST']['password'];

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

                if (!$user)
                    return new Render($templa_tename, [
                        'error' => 'credential invalids'
                    ], 403);
                    
                Util::authenticated($user['id'], false);

                $next = $request['POST']['_next'];
                return new RedirectResponse($next);
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
                }

                return new RedirectResponse($next);
            };
        }
    }