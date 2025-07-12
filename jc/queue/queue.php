<?php

    namespace jc\queue;

    define("QUEUE_DIR", __DIR__."/../storage/queue/");

    class Queue {
        public readonly string $id;
        protected string $connection;

        public function __construct(string $id) {
            $this->id = $id;
            $this->connection = QUEUE_DIR.hash('sha256', $id);

            if (!file_exists($this->connection)) {
                fclose(fopen($this->connection, "w"));
                chmod($this->connection, 0666);
            }
        }

        public function push(string $data) {
            $descriptor_spec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];

            proc_open("php ".__DIR__."/push.php {$this->connection} '$data'", $descriptor_spec, $pipes);
        }

        /**
         * @param callable(array $items): void $func
         * @return void
         */
        public function consumer(callable $func, $time = 0) {
            while (true) {
                if ($time) sleep($time);

                do {
                    $hundle = fopen($this->connection, 'r+');
                } while (!$hundle);

                flock($hundle, LOCK_EX);

                $size = $this->filesize($hundle);

                if ($size > 0) {
                    $content = fread($hundle, $size);

                    ftruncate($hundle, 0);

                    rewind($hundle);

                    fwrite($hundle, "");

                    fflush($hundle);
                } else {
                    $content = "";
                }

                flock($hundle, LOCK_UN);
                fclose($hundle);
                
                if ($content) {
                    $items = explode("\n", trim($content));

                    if (count($items))
                        $func($items);
                }
            }
        }

        protected function filesize($hundle) {
            fseek($hundle, 0, SEEK_END);
            $file_size = ftell($hundle);
            rewind($hundle);

            return $file_size;
        }
    }