<?php

    namespace jc\qbuilder;

    use mysqli;
    use mysqli_result;
    use SQLite3;
    use SQLite3Result;

    class QBuilder {
        protected mysqli|SQLite3|null $conn;
        protected string $dbconnection;
        protected array $lines = [];

        protected string $table = '';
        protected string $start = '';
        protected array $wheres = [];

        protected bool $is_insert = false;

        public function __construct(string $dbconnection, string $dbname, ?string $dbpassword = null, ?string $dbhost = null, ?string $dbuser = null, ?int $dbport = null) {            
            $this->dbconnection = $dbconnection;
            $this->conn = $this->connect($dbname, $dbpassword, $dbhost, $dbuser, $dbport);
        }

        public function table($table) {
            $this->table = $table;
            return $this;
        }

        public function where($where) {
            array_push($this->wheres, "($where)");
            return $this;
        }

        public function or_where($where) {
            array_push($this->wheres, "or ($where)");
            return $this;
        }

        public function and_where($where) {
            array_push($this->wheres, "and ($where)");
            return $this;
        }

        public function select(?array $attrs = null) {
            $attrs = $attrs?implode(',', array_map(function($item) {
                return $item;
            }, $attrs)):'*';

            $this->start = "SELECT $attrs FROM ".$this->table;
            return $this;
        }

        public function insert(array $data) {
            $keys = implode(',', array_map(function($item) {
                return "`$item`";
            }, array_keys($data)));

            $values = implode(',', array_map(function($item) {
                return self::prepare($item);
            }, $data));

            $this->is_insert = true;

            $this->start = "INSERT INTO ".$this->table."($keys) VALUES ($values)";
            return $this;
        }

        public function update(array $data) {
            $values = [];
            foreach ($data as $key => $value) {
                array_push($values, "`$key` = ".self::prepare($value));
            }
            $values = implode(',', $values);

            $this->start = "UPDATE ".$this->table." SET $values";

            return $this;
        }

        public function delete() {
            $this->start = "DELETE FROM ".$this->table;

            return $this;
        }

        public function execute() {
            $sql = $this->get_query();

            if ($this->conn instanceof mysqli) {
                $res = $this->conn->prepare($sql);
                $res->execute();
            } else if ($this->conn instanceof SQLite3) {
                $this->conn->exec($sql);
            }

            $this->is_insert = false;

            return null;
        }

        public function query() {
            $query = $this->get_query();

            if ($this->conn instanceof mysqli) {
                $result = mysqli_query($this->conn, $query);                
            } else if ($this->conn instanceof SQLite3) {
                $result = $this->conn->query($query);
            }

            $this->lines = [];
            while ($line = $this->fetch_array($result)) {
                array_push($this->lines, $line);
            }

            return $this;
        }

        public function all() {
            return $this->lines;
        }

        public function first() {
            return $this->lines[0];
        }

        public function exist() {
            if (count($this->lines) > 0) return true;
            return false;
        }

        protected function fetch_array(SQLite3Result | mysqli_result $result) {
            if ($result instanceof mysqli_result) {
                return mysqli_fetch_assoc($result);
            } else if ($result instanceof SQLite3Result) {
                return $result->fetchArray(SQLITE3_ASSOC);
            }

            return null;
        }

        protected function get_query() {
            if (count($this->wheres) > 0)
                $wheres = " WHERE ". implode(' ', $this->wheres);
            else
                $wheres = '';

            $sql = $this->start."$wheres";

            $this->table = '';
            $this->start = '';
            $this->wheres = [];

            return $sql;
        }

        public static function prepare($content) {
            $content = str_replace('\\', '\\\\', $content);
            $content = str_replace('\'', '\\\'', $content);

            return "'$content'";
        }

        protected function connect(string $dbname, ?string $dbpassword = null, ?string $dbhost = null, ?string $dbuser = null, ?int $dbport = null) {
            $conn = NULL;

            try {
                if ($this->dbconnection == 'mysql') {
                    $conn = new mysqli(
                        $dbhost,
                        $dbuser,
                        $dbpassword,
                        $dbname,
                        $dbport
                    );
                } else if ($this->dbconnection == 'sqlite') {
                    $conn = new SQLite3($dbname);
                }
            } catch (\Throwable $th) {
                //throw $th;
            }

            return $conn;
        }

        public function close() {
            if ($this->conn instanceof mysqli) {
                mysqli_close($this->conn);
            } else if ($this->conn instanceof SQLite3) {
                $this->conn->close();
            } 
        }
    }