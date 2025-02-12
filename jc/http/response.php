<?php

    namespace jc\response;

    use Exception;
    use Generator;
    use function jc\url_for;

    class Response {
        protected string|Generator $data;
        public ?int $status_code;
        protected array $headers;
        protected array $cookies;
        public string $content_type;

        /**
         * Summary of __construct
         * @param string|Generator<int, string> $data
         * @param int $status_code
         * @param array $headers
         * @param array $cookies
         */
        public function __construct(string|Generator $data, int $status_code = null, array $headers = [], array $cookies = []) {
            $this->data         = $data;
            $this->status_code  = $status_code;
            $this->headers      = $headers;
            $this->cookies      = $cookies;
            $this->content_type = 'text/html';
        }

        public function get_data() {
            return $this->data;
        }

        public function get_cookies() {
            return $this->cookies;
        }

        public function set_cookie($key, $value) {
            $this->cookies[$key] = $value;
        }

        public function remove_cookie($key) {
            setcookie($key, '', 1, '/');
        }

        public function get_headers() {
            return $this->headers;
        }

        public function set_header($key, $value) {
            $this->headers[$key] = $value;
        }
    }

    class JSONResponse extends Response {
        /**
         * Summary of __construct
         * @param mixed $data
         * @param int $status_code
         * @param array $headers
         * @param array $cookies
         */
        public function __construct($data, int $status_code = null, array $headers = [], array $cookies = []) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            parent::__construct($data, $status_code, $headers, $cookies);

            $this->content_type = 'application/json';
        }
    }

    class StreamingResponse extends Response {
        /**
         * Summary of __construct
         * @param callable(): Generator<int, string> $function
         * @param int $status_code
         * @param array $headers
         * @param array $cookies
         */
        public function __construct(callable $function, int $status_code = null, array $headers = [], array $cookies = []) {
            parent::__construct($function(), $status_code, $headers, $cookies);
        
            $this->set_header('Cache-Control', 'no-cache');
        }
    }

    class FILEResponse extends StreamingResponse {
        /**
         * Summary of __construct
         * @param string $file
         * @param int $status_code
         * @param array $headers
         * @param array $cookies
         * @throws Exception
         */
        public function __construct(string $file, int $status_code = null, array $headers = [], array $cookies = []) {
            if (!file_exists($file) || !is_file($file))
                throw new Exception("File not found", 1);
            
            $handle = fopen($file, "rb");

            if (!$handle) 
                throw new Exception("Error to open file", 1);

            parent::__construct(
                function() use ($handle) {
                    $tam = 1024 * 1024;

                    while (!feof($handle)) {
                        yield fread($handle, $tam);
                    }

                    fclose($handle);
                },
                $status_code,
                $headers,
                $cookies
            );

            $this->set_header('Content-Length', filesize($file));

            $this->content_type = mime_content_type($file);   
        }
    }

    class RedirectResponse extends Response {
        /**
         * Summary of __construct
         * @param string $url
         * @param int $status_code
         * @param array $headers
         * @param array $cookies
         */
        public function __construct(string $url, int $status_code = 302, array $headers = [], array $cookies = []) {            
            parent::__construct('', $status_code, $headers, $cookies);
            $this->set_header('location', $url);
        }
    }

    class Render extends Response {
        private static $vars = [];

        /**
         * Summary of __construct
         * @param string $template
         * @param array $context
         * @param int $status_code
         * @param array $headers
         * @param array $cookies
         */
        public function __construct(string $template, array $context = [], int $status_code = null, array $headers = [], array $cookies = []) {
            $data = self::render($template, $context);

            parent::__construct($data, $status_code, $headers, $cookies);
        }

        public static function render(string $template, array $context) {
            global $template_folder_default;
            
            $template1 = $template;
            $template = "$template_folder_default{$template}.html";

            if (!file_exists($template) || !is_file($template))
                throw new Exception("Template '$template1' not found", 1);
            
            $lines = [];

            foreach (file($template) as $line) {
                if (strlen(trim($line)) == 0);
                else if (trim($line)[0] != "@" || substr(trim($line), 0, 2) == "@@") {
                    if (trim($line)[0] == "@")
                        $line = substr(trim($line), 1, strlen(trim($line)));
                    $line = str_replace("'", "\\'", $line);
                    $line = str_replace("{{", "'.", $line);
                    $line = str_replace("}}", ".'", $line);
                    $line = 'array_push($renderizado, \''.trim($line).'\');';
                } else {
                    $line = substr(trim($line), 1, strlen(trim($line)));
                }
                array_push($lines, trim($line));
            }

            $renderizado = [];

            $context = array_merge(self::$vars, $context);
            foreach ($context as $key => $_)
                eval('$'.$key.' = $context["'.$key.'"];');

            try {
                eval(implode("\n", $lines));
            } catch (\Throwable $th) {
                throw new Exception("Error to render '$template1' template", 1);
            }

            return implode("\n", $renderizado);
        }

        public static function add_var($key, $value) {
            self::$vars[$key] = $value;
        }
    }

    Render::add_var("include", function($template, $context = []) {
        return Render::render($template, $context);
    });

    Render::add_var("url", function(string $name, $params = [], $idx = 0) {
        return url_for($name, $params, $idx);
    });

    Render::add_var("static", function(string $filename) {
        global $static_folder_default;

        return getenv('URL')."/$static_folder_default$filename";
    });