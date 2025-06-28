<?php
    namespace jc;

    use ArrayAccess;
    use Error;

    class File {
        public readonly string $name;
        public readonly string $full_path;
        public readonly int $size;
        public readonly bool $error;
        public readonly string $real_name;
        protected string $tmp_name;
        public static string $upload_file;

        public function __construct($attributes) {
            $this->name      = self::securiti_name($attributes["name"]);
            $this->real_name = $attributes["name"];
            $this->full_path = $attributes["full_path"];
            $this->tmp_name  = $attributes["tmp_name"];
            $this->size      = $attributes["size"];
            $this->error     = (bool) $attributes["error"];
        }

        public function save(?string $file_name = null) {
            $to = $file_name??self::$upload_file."/{$this->name}";
            
            return move_uploaded_file($this->tmp_name, $to);
        }

        public static function securiti_name(string $name, ?string $in = null): string {
            $dir      = $in??self::$upload_file;
            $basename = $name;
            
            while (true) {
                if (!file_exists("$dir/$name")) return $name;

                $name = substr(hash("sha256", $name), -4).$basename;
            }
        }
    }

    File::$upload_file = getenv("UPLOADFILE");

    class Request implements ArrayAccess {
        public readonly ?array $post;
        public readonly array $get;
        public readonly ?array $queryparams;
        public readonly array $cookies;
        public readonly string $method;
        public readonly array $headers;
        public readonly string $base_url;
        public readonly string $url;
        public readonly string $host;
        public readonly string $address;
        public readonly int $port;
        public readonly string $protocol;
        public readonly string $url_path;

        private readonly array $attributes;

        public function __construct($attributes) {
            $this->post = $attributes['POST'];
            $this->get = $attributes['GET'];
            $this->queryparams = $attributes['QUERYPARAMS'];
            $this->cookies = $attributes['COOKIE'];
            $this->method = $attributes['METHOD'];
            $this->headers = $attributes['HEADERS'];
            $this->base_url = $attributes['BASE_URL'];
            $this->url = $attributes['URL'];
            $this->host = $attributes['HOST'];
            $this->address = $attributes['ADDRESS'];
            $this->port = $attributes['PORT'];
            $this->protocol = $attributes['PROTOCOL'];
            $this->url_path = $attributes['URL_PATH'];

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

        /**
         * @return File[]
         */
        public function get_files(): array {
            static $files;

            if (!isset($files)) {
                foreach ($_FILES as $key => $file) {
                    $files[$key] = new File($file);
                }
            }

            return $files;
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

    class RealTime extends Request {
        public readonly string $connection;
        public readonly string $connection_id;

        public function __construct($attributes) {
            $this->connection = $attributes["CONNECTION"];

            $this->connection_id = hash('sha256', json_encode($attributes));

            parent::__construct($attributes);
        }

        public function wait_accept() {
            ignore_user_abort(true);

            $hundle = fopen($this->connection, 'r+');
            flock($hundle, LOCK_EX);

            $real_time = fread($hundle, $this->filesize($hundle));
            $real_time = json_decode($real_time, true);
            array_push($real_time["connections"], $this->connection_id);

            $this->write($hundle, $real_time);

            header("Content-Type: text/event-stream");
            header("Cache-Control: no-cache");
            header("Connection: keep-alive");
            
            ob_start();
            echo "data: {$this->connection_id}\n\n";
            ob_flush();
            flush();
        }

        public function wait_receive(): string|null {
            while (true) {
                
                do {
                    $hundle = fopen($this->connection, 'r+');
                } while (!$hundle);

                flock($hundle, LOCK_EX);

                $real_time = fread($hundle, $this->filesize($hundle));
                $real_time = json_decode($real_time, true);
                
                if (connection_aborted()) {
                    $real_time["connections"] = array_filter($real_time["connections"], fn($connection) => $connection != $this->connection_id);

                    $this->write($hundle, $real_time);
                    
                    if (count($real_time["connections"]) == 0)
                        unlink($this->connection);

                    return null;
                }

                if ($real_time["message"] && in_array($this->connection_id, $real_time["message"]["to"]) && !in_array($this->connection_id, $real_time["message"]["views"])) {
                    array_push($real_time["message"]["views"], $this->connection_id);
                    
                    $this->write($hundle, $real_time);

                    echo "data: {$real_time['message']['content']}\n\n";
                    ob_flush();
                    flush();
                } else if (isset($real_time[$this->connection_id])) {
                    $message = $real_time[$this->connection_id];

                    unset($real_time[$this->connection_id]);

                    $this->write($hundle, $real_time);

                    return $message;
                } else {
                    echo "data: Are you ok\n\n";
                    ob_flush();
                    flush();
                }
            }
        }

        public function wait_receive_json(): array|null {
            $data = $this->wait_receive();

            if ($data === null) return null;

            return json_decode($data, true);
        }

        public function wait_send(string $data, array $to) {
            $hundle = fopen($this->connection, 'r+');
            flock($hundle, LOCK_EX);

            $real_time = fread($hundle, $this->filesize($hundle));
            $real_time = json_decode($real_time, true);

            $real_time["message"] = [
                "content" => $data,
                "to"      => $to,
                "views"   => []
            ];

            $this->write($hundle, $real_time);
        }

        public function wait_send_json(array $data, array $to) {
            $this->wait_send(json_encode($data, true), $to);
        }

        public function get_connections() {
            
            do {
                $hundle = fopen($this->connection, 'r+');
            } while (!$hundle);

            flock($hundle, LOCK_EX);

            $real_time = fread($hundle, $this->filesize($hundle));
            
            $real_time = json_decode($real_time, true);

            flock($hundle, LOCK_UN);

            fclose($hundle);

            return $real_time["connections"];
        }

        public function write($hundle, $content) {
            ftruncate($hundle, 0);

            rewind($hundle);

            fwrite($hundle, json_encode($content, true));

            fflush($hundle);
            flock($hundle, LOCK_UN);

            fclose($hundle);
        }

        public function filesize($hundle) {
            fseek($hundle, 0, SEEK_END);
            $file_size = ftell($hundle);
            rewind($hundle);

            return $file_size;
        }
    }