<?php
    require __DIR__."/helpers.php";

    function assert_($condition) {
        if (!$condition)
            throw new Exception("Assert Error", 1);
    }

    function assert_equal($op1, $op2) {
        if (!($op1 === $op2))
            throw new Exception("'$op1' is not equal to '$op2'", 1);
    }

    function assert_different($op1, $op2) {
        if ($op1 === $op2)
            throw new Exception("'$op1' is not different to '$op2'", 1);
    }

    function assert_in($op1, array|string $op2) {
        $condition = is_string($op2)?str_contains($op2, $op1):in_array($op1, $op2, true);
        
        if (!$condition) {
            $list = is_string($op2)?$op2:'['.implode(', ', $op2).']';
            throw new Exception("'$op1' is not in '$list'", 1);
        }
    }

    function assert_not_in($op1, array|string $op2) {
        try {
            assert_in($op1, $op2);
            $list = is_string($op2)?$op2:'['.implode(', ', $op2).']';

            throw new Exception("'$op1' is in '$list'", 1);
        } catch (\Throwable $th) {}
    }

    function assert_greater($op1, $op2) { 
        if ($op1 < $op2)
            throw new Exception("'$op1' is not greater than '$op2'", 1);
    }

    function assert_less($op1, $op2) { 
        if ($op1 > $op2)
            throw new Exception("'$op1' is not less than '$op2'", 1);
    }

    function assert_greater_equal($op1, $op2) { 
        if ($op1 < $op2 && !($op1 === $op2))
            throw new Exception("'$op1' is not greater than or equal to '$op2'", 1);
    }

    function assert_less_equal($op1, $op2) { 
        if ($op1 > $op2 && !($op1 === $op2))
            throw new Exception("'$op1' is not less than or equal to '$op2'", 1);
    }

    class Response {
        protected $data;

        public readonly int $status_code;
        public readonly array $headers;

        public function __construct($data, int $status_code, $headers) {
            $this->data = $data;
            $this->status_code = $status_code;
            $this->headers = $headers;
        }

        public function json() {
            return json_decode($this->data, true);
        }

        public function text() {
            return (string) $this->data;
        }
    }

    class Request {
        protected static string $base_uri = "";
        protected static array $default_headers = [];

        public static function get(string $uri, array $headers = []) {
            return self::request($uri, [], $headers, "GET");
        }

        public static function post(string $uri, array $data = [], array $headers = []) {
            return self::request($uri, $data, $headers, "POST");
        }

        public static function delete(string $uri, array $headers = []) {
            return self::request($uri, [], $headers, "DELETE");
        }

        public static function put(string $uri, array $data = [], array $headers = []) {
            return self::request($uri, $data, $headers, "PUT");
        }

        protected static function url(string $url) {
            $pattern = '/^(http[s]?:\/\/'.preg_quote(getenv("DOMAIN"), "/").')(\/.+)?$/';

            if (!str_starts_with($url, "http") && !empty(self::$base_uri)) {
                $url = self::$base_uri."$url";
            }

            if (preg_match($pattern, $url, $mathes)) {
                $matheend = $mathes[2]??'';
                $url = "{$mathes[1]}/test{$matheend}";
            }

            return $url;
        }

        protected static function request(string $uri, array $data = [], array $headers = [], $method = "GET") {
            $ch = curl_init(self::url($uri));
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);

            if ($method === "POST")
                curl_setopt($ch, CURLOPT_POST, true);
            else if ($method !== "GET")
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            
            if (count($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }

            if (count(self::$default_headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(fn($key, $value) => "$key: $value", array_keys(self::$default_headers), self::$default_headers));
            }

            if (count($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(fn($key, $value) => "$key: $value", array_keys($headers), $headers));
            }


            $response = curl_exec($ch);
            
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);

            return new Response(
                self::get_body($ch, $response),
                $status_code,
                self::get_headers($ch, $response)
            );
        }

        protected static function get_headers($ch, $response) {
            $header_size  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headers_text = substr($response, 0, $header_size);

            $headers = [];

            $lines = explode("\r\n", $headers_text);

            foreach ($lines as $line) {
                if (strpos($line, ": ") !== false) {
                    list($key, $value) = explode(": ", $line, 2);
                    $headers[$key] = $value;
                }
            }

            return $headers;
        }

        protected static function get_body($ch, $response) {
            $header_size  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            
            return substr($response, $header_size);
        }

        public static function set_base_uri(string $uri) {
            self::$base_uri = $uri;
        }

        public static function set_default_headers(array $headers) {
            self::$default_headers = $headers;
        }
    }

    /**
     * @property TestCase[] $tests_case
     * @property array<string, callable> $tests
     */
    class TestCase {
        protected static array $tests_case = [];

        protected array $tests;
        protected string $name_case;

        public function __construct(string $name_case) {
            array_push(self::$tests_case, $this);

            $this->name_case = $name_case;

            $this->start();
        }

        public function start() {}

        public function add_test(string $name_test, callable $test) {
            $this->tests[$name_test] = $test;
        }

        protected static function set_up_test() {
            up_env();

            $migration = getenv("MIGRATION");

            if (!$migration) {
                echo "Variavel de ambiente MIGRATION não foi definido";
                exit(1);
            }

            if (file_exists(DB_TEST))
                unlink(DB_TEST);

            $code = 0;
            $output = [];

            exec("export TEST=true && php $migration", $output, $code);
            exec("sudo chmod 777 ".DB_TEST, $output);

            if ($code != 0) {
                echo implode("\n", $output);

                exit($code);
            }
        }

        public function set_up() {}

        public static function run() {
            self::set_up_test();
            
            $errors   = '';
            $tests    = [];
            $n_oks    = 0;
            $n_errors = 0;
            $erro     = 0;

            foreach (self::$tests_case as $test_case) {
                $error = false;

                try {
                    $test_case->set_up();
                } catch (\Throwable $th) {
                    $errors .= "{$test_case->name_case}: Setup not runed\n\n";

                    $erro = 1;
                    continue;
                }

                foreach ($test_case->tests as $name_test => $test) {
                    try {
                        $test();

                        $n_oks++;

                        array_push($tests, '.');
                    } catch (\Throwable $th) {
                        if (!$error) {
                            $n_errors++;
                            $errors .= $name_test;
                            $error = true;
                        }

                        $errors .= "\n\n      ".$th->getMessage();

                        array_push($tests, 'x');
                    }
                    
                    echo chr(27).'c';

                    echo implode(' ', $tests);

                    $n_tests = count($tests);
                    echo "\n\nRun {$n_tests} test(s)";
                    
                    echo "\n\nOk: $n_oks";
                    echo "\nErrors: $n_errors";

                    echo "\n\n"; 

                    echo $errors;
                }

                if (!empty($errors)) $errors .= "\n\n";
            }

            echo "\n";

            exit($erro || $n_errors);
        }
    }
