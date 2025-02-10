<?php
    namespace jc;

    require __DIR__.'/util.php';
    require __DIR__.'/qbuilder.php';
    require __DIR__.'/pages.php';
    require __DIR__.'/middleware.php';

    use ArrayAccess;
    use Error;
    use Exception;
    use jc\middleware\Middleware;

    $template_folder_default = '';
    $static_folder_default = '';

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

    // increasing environment variables
    if (file_exists('.env'))
        foreach (file('.env') as $line) {
            if (empty(trim($line)) || str_starts_with($line, '#')) continue;
            
            $split = explode('=', $line, 2);

            if (count($split) == 2)
                putenv(trim($split[0]).'='.trim($split[1]));
        }

    $BASEURL  = getenv('URL');
    $parseurl = parse_url($BASEURL);
    $PREFIX   = $parseurl["path"] ?? "";

    $routes_names = [];

    function url_for(string $name, array $params = [], int $idx = 0) {
        global $BASEURL;
        global $routes_names;

        if (!isset($routes_names[$name]))
            throw new Exception("ROUTE '$name' IS NOT DEFINED", 1);

        if (is_string($routes_names[$name]))
            $path = $routes_names[$name];
        else
            $path = $routes_names[$name][$idx];

        foreach ($params as $key => $value)
            $path = str_replace('{'.$key.'}', $value, $path);

        return $BASEURL.$path;
    }

    require __DIR__.'/response.php';

    class JCRoute {
        protected array $middlewares = [];
        public array $routes = [];

        public function get(string | array $path, array $params, callable $view) {
            $params['methods'] = ['GET'];
            $this->route($path, $params, $view);
        }

        public function post(string | array $path, array $params, callable $view) {
            $params['methods'] = ['POST'];
            $this->route($path, $params, $view);
        }

        public function delete(string | array $path, array $params, callable $view) {
            $params['methods'] = ['DELETE'];
            $this->route($path, $params, $view);
        }

        public function put(string | array $path, array $params, callable $view) {
            $params['methods'] = ['PUT'];
            $this->route($path, $params, $view);
        }

        public function route(string | array $path, array $params, callable $view) {
            if (!isset($params['methods']))
                $params['methods'] = ['GET', 'POST', 'DELETE', 'PUT'];
            
            $params['middlewares'] = $params['middlewares'] ?? [];

            foreach (array_merge(array_reverse($params['middlewares']), array_reverse($this->middlewares)) as $middleware) {
                $view = $middleware($view);
            }

            $route = [
                'path'          => $path,
                'methods'       => $params['methods'],
                'view'          => $view,
                'response_code' => $params['response_code'] ?? null
            ];

            if (isset($params['name']))
                $this->routes[$params['name']] = $route;
            else
                array_push($this->routes, $route);
        }

        public function include_route(JCRoute $route, string $prefix = '') {         
            foreach ($route->routes as $key => $value) {
                if (is_string($value['path']))
                    $value['path'] = $prefix.$value['path'];
                else
                    foreach ($value['path'] as $key => $_) {
                        $value['path'][$key] = $prefix.$value['path'][$key];
                    }

                foreach (array_reverse($this->middlewares) as $middleware) {
                    $value['view'] = $middleware($value['view']);
                }
                    
                if (is_string($key)) $this->routes[$key] = $value;
                else array_push($this->routes, $value);
            }
        }

        public function add_middleware(callable $middleware) {
            array_push($this->middlewares, $middleware);
        }
    }

    class Jc extends JCRoute {
        public function __construct(string $static_folder = 'static/', string $template_folder = 'templates/') {
            global $template_folder_default;
            global $static_folder_default;

            $static_folder_default = $static_folder;
            $template_folder_default = $template_folder;

            $this->add_middleware(Middleware::adaptresponse());
        }

        public function run() {
            global $PREFIX;
            global $static_folder_default;

            $URI = urldecode($_SERVER["REQUEST_URI"]);

            $separator = $PREFIX."/$static_folder_default";
            $split_uri = explode($separator, $URI);

            if (count($split_uri) > 1 && $split_uri[0] == '') {
                unset($split_uri[0]);

                $file_name = $static_folder_default.implode($separator, $split_uri);
                
                if (file_exists($file_name)) {
                    $content = file_get_contents($file_name);

                    $content_type = mime_content_type($file_name);
                    $extention = explode('.', $file_name)[count(explode('.', $file_name)) - 1];

                    if ($content_type == 'text/plain' && in_array($extention, ['html', 'css', 'js']))
                        header("Content-Type:text/$extention");
                    else
                        header('Content-Type:'.$content_type);

                    echo $content;
                    exit(0);
                }
            }

            global $routes_names;

            foreach ($this->routes as $nameroute => $route) {
                if (is_string($nameroute)) {
                    $routes_names[$nameroute] = $route['path'];
                }
            }

            $this->adjust_request();

            $METHOD = $_SERVER["REQUEST_METHOD"];

            $NOMETHOD  = true;
            $NOTFOUND  = true;
            $NOPROSSES = false;

            $stop = false;

            if (getenv('DEV') != 'false' && count($this->routes) == 0) {
                $this->get('/', [], function() {
                    global $basehtml;
                    return str_replace(
                        [
                            '{{title}}',
                            '{{content}}',
                            'color: #111',
                            'background: #555'
                        ],

                        [
                            'JcApp',
                            '<h2>WELCOME TO JC</h2><p>Use this framework to develop web sites fast</p>',
                            'color: #f00',
                            'background: #111'
                        ],
                        
                        $basehtml
                    );
                });
            }

            foreach ($this->routes as $nameroute => $route) {
                if (!is_array($route["path"]))
                    $route["path"] = [$route["path"]];

                foreach ($route["path"] as $path) {
                    if ($variables = $this->urlComp($PREFIX.$path, $URI)) {
                        if ($variables == 1) $variables = [];
                        $NOTFOUND = false;

                        // adjusting request methods
                        if ($METHOD == 'POST') {
                            if (isset($_POST['_method'])) {
                                $METHOD = strtoupper($_POST['_method']);
                                unset($_POST['_method']);
                            }
                        }
                        
                        if (!in_array($METHOD, $route["methods"])) {
                            continue;
                        }

                        $NOMETHOD = false;

                        $request = new Request([
                            'GET'         => $variables,
                            'QUERYPARAMS' => $_GET,
                            'POST'        => $_POST,
                            'COOKIE'      => $_COOKIE,
                            'METHOD'      => $METHOD,
                            'HEADERS'     => getallheaders(),
                            'BASE_URL'    => getenv('URL')
                        ]);

                        try {
                            if (isset($route['response_code'])) http_response_code($route['response_code']);

                            $response = $route["view"]($request);
                        } catch (\Throwable $th) {
                            $NOPROSSES = (string) $th;
                            break;
                        }

                        $stop = true;

                        header('Content-Type:'.$response->content_type);
                        if ($response->status_code) http_response_code($response->status_code);
                        foreach ($response->get_headers() as $key => $value) {
                            header($key.':'.$value);
                        }
                        foreach ($response->get_cookies() as $key => $value) {
                            setcookie($key, $value, 0, '/');
                        }

                        echo $response->get_data();
    
                        exit(0);
                    }
                }
                if ($stop) break;
            }

            global $basehtml;

            if ($NOTFOUND) {
                echo str_replace(['{{title}}', '{{content}}'], ['404 NOT FOUND', '<h2><span class = "code">404</span> PAGE NOT FOUND</h2>'], $basehtml);
                http_response_code(404);
            } else if ($NOMETHOD) {
                echo str_replace(['{{title}}', '{{content}}'], ['405 METHOD NOT ALLOWED', '<h2><span class = "code">405</span> METHOD NOT ALLOWED</h2>'], $basehtml);
                http_response_code(405);
            } else if ($NOPROSSES) {
                echo str_replace(['{{title}}', '{{content}}'], ['500 INTERN ERROR', getenv('DEV')=='false'?'<h2><span class = "code">500</span> INTERN SERVER ERROR</h2>':"<div><pre>$NOPROSSES</pre></div>"], $basehtml);
                http_response_code(500);
            }

            exit(1);
        }

        protected function adjust_request() {
            $HEADERS = getallheaders();
            if (isset($HEADERS["content-type"])) {
                $CONTENTTYPE = $HEADERS["content-type"];
                if ($CONTENTTYPE == "application/json") {
                    $json_data = file_get_contents("php://input");

                    $_POST = json_decode($json_data, true);
                }
            } else {
                if (count($_POST) == 0) {
                    $json_data = file_get_contents("php://input");

                    $_POST = json_decode($json_data, true);
                }
            }
        }

        /**
         * @param string&&string
         * 
         * @return array||bool 
         * 
         * compara duas url e se baseando no formato
         * da url1, ela extrai valores da url2.
        */
        protected function urlComp(string $url1, string $url2): array|bool {
            if ($url2[strlen($url2) - 1] == '/') {
                $url2 = substr($url2, 0, strlen($url2) - 1);
            }
            if ($url1[strlen($url1) - 1] == '/') {
                $url1 = substr($url1, 0, strlen($url1) - 1);
            }
    
            if (count(explode("/", $url1)) != count(explode("/", $url2))) {
                return false;
            }
    
            $variables = [];
            $patternVariable = "/{(.*?)}/";
    
            if (preg_match_all($patternVariable, $url1, $matches)) {
                $url1 = preg_replace($patternVariable, "(.*?)", $url1);
                $variables = $matches[1];
            }
    
            $patternRoute = '/^'.str_replace("/", "\/", $url1).'$/';
    
            if (preg_match($patternRoute, $url2, $matches)) {
                unset($matches[0]);
    
                $keys = $variables;
                $variables = array_combine($keys, $matches);
    
                if (count($variables) == 0) return true;
    
                return $variables;
            } else {
                return false;
            }
        }
    }

    class Request implements ArrayAccess {
        public readonly ?array $post;
        public readonly array $get;
        public readonly ?array $queryparams;
        public readonly array $cookies;
        public readonly string $method;
        public readonly array $headers;
        public readonly string $base_url;

        private readonly array $attributes;

        public function __construct($attributes) {
            $this->post = $attributes['POST'];
            $this->get = $attributes['GET'];
            $this->queryparams = $attributes['QUERYPARAMS'];
            $this->cookies = $attributes['COOKIE'];
            $this->method = $attributes['METHOD'];
            $this->headers = $attributes['HEADERS'];
            $this->base_url = $attributes['BASE_URL'];

            $this->attributes = $attributes;
        }

        public function get_header($key) {
            return $this->headers[$key] ?? null;
        }

        public function get_cookie($key) {
            return $this->cookie[$key] ?? null;
        }

        public function data() {
            if ($this->method == 'GET') 
                return $this->get;

            return $this->post;
        }

        public function get_query_param($key) {
            return $this->queryparams[$key] ?? null;
        }

        public function offsetSet($_offset, $_value): void {
            throw new Error("Uncaught Error: Type Request is not mutable", 1);
        }

        public function offsetExists($offset): bool {
            return isset($this->attributes[$offset]);
        }

        public function offsetUnset($_offset): void {
            throw new Error("Uncaught Error: Type Request is not mutable", 1);
        }

        public function offsetGet($offset): mixed {
            if (isset($this->attributes[$offset]))
                return $this->attributes[$offset];

            throw new Error("Uncaught Error: Cannot access property '$offset' on Request", 1);
        }
    }