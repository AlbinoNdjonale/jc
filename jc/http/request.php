<?php
    namespace jc;

    use ArrayAccess;
    use Error;

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
