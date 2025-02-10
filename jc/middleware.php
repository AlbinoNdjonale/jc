<?php
    namespace jc\middleware;

    use jc\qbuilder\QBuilder;
    use jc\response\JSONResponse;
    use jc\response\RedirectResponse;
    use jc\response\Response;
    use jc\response\Render;
    use jc\util\Util;

    $basehtml = '
        <!DOCTYPE html>
        <html lang = "en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{{title}}</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                }

                body {
                    height: 100vh;
                    font-family: arial, sans;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    padding: 0 10px;
                    color: #111;
                    background: #555;
                }

                h2 {
                    text-align: center;
                    font-size: 35px;
                }

                .code {
                    border-right: 2px solid red;
                    padding-right: 10px;
                }

                div {
                    background: #111;
                    color: red;
                    padding: 20px;
                    border-radius: 5px;
                    max-width: 400px;
                    overflow: auto;
                    max-height: 400px;
                }

                pre {
                    white-space: pre-wrap;
                }
            </style>
        </head>
        <body>
            {{content}}
        </body>
        </html>
    ';

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

    class Middleware {
        public static function cors(array $params) {
            return function(callable $view) use ($params) {
                return function($request) use ($params, $view) {
                    $response = $view($request);

                    foreach ($params as $key => $value) {
                        $response->set_header('access-control-'.$key, $value);
                    }

                    return $response;
                };
            };
        }

        public static function csrftoken() {
            Render::add_var("csrftoken", function() {
                $db = db();

                $random = Util::randomword();

                $csrftoken = hash('sha256', $random.getenv('SECRETKEY'));

                if (isset($_COOKIE['csrftoken']) && $db->table('token')->select()->where('id = '.QBuilder::prepare($_COOKIE['csrftoken']))->query()->exist()) {
                    $db->table('token')->update([
                        'content' => $csrftoken
                    ])->where('id = '.QBuilder::prepare($_COOKIE['csrftoken']))->execute();
                    
                    $csrftoken = [
                        'content' => $csrftoken
                    ];
                } else {
                    $db->table('token')->insert([
                        'content' => $csrftoken,
                        'csrf'    => true
                    ])->execute();

                    $csrftoken = $db->table('token')->select(['id', 'content'])->where("content = '$csrftoken'")->query()->first();
                    setcookie('csrftoken', $csrftoken['id'], 0, '/');
                }

                $db->close();
                
                return "<input hidden type = 'text' value = '".$csrftoken['content']."' name = '_csrftoken'>";
            });

            return function(callable $view) {
                return function($request) use ($view) {
                    if ($request['METHOD'] != 'GET' && $request['METHOD'] != 'OPTION') {
                        global $basehtml;

                        if (!isset($request['POST']['_csrftoken']))
                            return new Response(
                                str_replace(
                                    ['{{title}}', '{{content}}'],
                                    ['401 UNAUTHORIZED', getenv('DEV')=='false'?'<h2><span class = "code">401</span> UNAUTHORIZED</h2>':'<div><pre>You must include \'{{$csrftoken()}}\' in your form</pre></div>'],
                                    $basehtml
                                ),
                                401
                            );

                        $db = db();

                        $csrftoken = $request['POST']['_csrftoken'];
                        unset($request['POST']['_csrftoken']);

                        $csrftokenexist = $db->table('token')
                            ->select()
                            ->where('content ='.Qbuilder::prepare($csrftoken))
                            ->and_where('csrf = true')
                            ->query()
                            ->exist();

                        if (!$csrftokenexist) {
                            $db->close();

                            return new Response(
                                str_replace(
                                    ['{{title}}', '{{content}}'],
                                    ['401 UNAUTHORIZED', getenv('DEV')=='false'?'<h2><span class = "code">401</span> UNAUTHORIZED</h2>':"<div><pre>The csrftoken '$csrftoken' is not valid</pre></div>"],
                                    $basehtml
                                ),
                                401
                            );
                        }

                        $db->close();
                    }

                    return $view($request);
                };
            };
        }

        public static function authuser() {
            return function(callable $view) {
                return function($request) use ($view) {
                    $tokeninvalid = false;
                    if (isset($request['COOKIE']['token'])) {
                        $db = db();

                        $token = $db->table('token')->select()->where('content = '.QBuilder::prepare($request['COOKIE']['token']))->query()->first();

                        if ($token) {
                            $user = $db->table('user')->select()->where('id = '.$token['user'])->query()->first();

                            Render::add_var("user", $user);
                            Util::set_user($user);
                        } else {
                            $tokeninvalid = true;
                            Render::add_var("user", [
                                'is_active' => 0
                            ]);
                        }
                    }

                    $response = $view($request);

                    if ($tokeninvalid) {
                        $response->remove_cookie('token');
                    }

                    return $response;
                };
            };
        }

        public static function login_required($url_login = null) {
            return function($view) use ($url_login) {
                return function($request) use ($view, $url_login) {
                    $user = Util::get_user();

                    if (!$user) return new RedirectResponse($url_login ?? getenv('URL').getenv('URLLOGIN'));

                    return $view($request);
                };
            };
        }

        public static function adaptresponse() {
            return function(callable $view) {
                return function($request) use ($view) {
                    $response = $view($request);

                    if ($response instanceof Response)
                        return $response;

                    if (is_array($response)) {
                        if (is_array($response[0]))
                            $response = new JSONResponse($response[0], $response[1] ?? null);
                        else
                            $response = new Response($response[0], $response[1] ?? null);

                        return $response;
                    }
                    
                    return new Response($response);
                };   
            };
        }
    }