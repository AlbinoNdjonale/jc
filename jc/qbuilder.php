<?php

    namespace jc\qbuilder;

    use mysqli;
    use mysqli_result;
    use SQLite3;
    use SQLite3Result;
    use Error;

    class QBuilder {
        protected mysqli|SQLite3|null $conn;
        protected string $dbconnection;
        protected array $lines = [];

        protected string $table = '';
        protected string $start = '';
        protected array $wheres = [];
        protected array $ons = [];
        protected ?int $limit_ = null;
        protected int $offset_ = 0;

        public function __construct(string $dbconnection, string $dbname, ?string $dbpassword = null, ?string $dbhost = null, ?string $dbuser = null, ?int $dbport = null) {
            $this->dbconnection = $dbconnection;

            $this->conn = $this->connect(
                $dbname,
                $dbpassword,
                $dbhost,
                $dbuser,
                $dbport
            );
            
        }

        public function table($table) {
            $this->table = $table;
            return $this;
        }

        public function join($table, $on) {
            $this->table = "{$this->table} join $table";

            array_push($this->ons, $on);

            return $this;
        }

        protected function _where($operator, ...$wheres) {
            $where = $this->verifysqlinject(implode(' and ', $wheres));

            array_push($this->wheres, "$operator($where)");

            return $this;
        }

        public function where(...$wheres) {
            $or = count($this->wheres) == 0?'':'or ';

            return $this->_where($or, ...$wheres);
        }

        public function or_where(...$wheres) {
            return $this->_where('or ', ...$wheres);
        }

        public function and_where(...$wheres) {
            return $this->_where('and ', ...$wheres);
        }

        public function select(?array $attrs = null) {
            $attrs = $attrs?implode(',', array_map(function($item) {
                return $item;
            }, $attrs)):'*';

            $this->start = "SELECT $attrs FROM ".$this->table;
            return $this;
        }

        public function exists() {
            $this->start = 'exists';
            return $this;
        }

        public function count() {
            $this->select(['COUNT(*)']);

            return $this;
        }

        public function insert(array $data) {
            $keys = implode(',', array_map(function($item) {
                return "`$item`";
            }, array_keys($data)));

            $values = implode(',', array_map(function($item) {
                return $this->verifysqlinject(self::prepare($item));
            }, $data));

            $this->start = "INSERT INTO ".$this->table."($keys) VALUES ($values)";
            return $this;
        }

        public function update(array $data) {
            $values = [];
            foreach ($data as $key => $value) {
                array_push($values, "`$key` = ".$this->verifysqlinject(self::prepare($value)));
            }
            $values = implode(',', $values);

            $this->start = "UPDATE ".$this->table." SET $values";

            return $this;
        }

        public function delete() {
            $this->start = "DELETE FROM ".$this->table;

            return $this;
        }

        public function limit(int $value) {
            $this->limit_ = $value;

            return $this;
        }

        public function offset(int $value) {
            $this->offset_ = $value;

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

        public function value() {
            return array_values($this->lines[0])[0];
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

        public function get_query() {
            if (count($this->wheres) > 0)
                $wheres = " WHERE ".implode(' ', $this->wheres);
            else
                $wheres = '';

            if (count($this->ons) > 0)
                $ons = " ON ".implode(' ON ', $this->ons);
            else
                $ons = '';

            $limit = $this->limit_?" LIMIT {$this->limit_}":'';
            $offset = $this->limit_?" OFFSET {$this->offset_}":'';

            if ($this->start == 'exists')
                $sql = "SELECT EXISTS(SELECT 1 FROM {$this->table}$wheres{$limit}{$offset}{$ons})";
            else
               $sql = "{$this->start}$wheres{$limit}{$offset}{$ons}";

            $this->table = '';
            $this->start = '';
            $this->wheres = [];
            $this->offset_ = 0;
            $this->limit_ = null;

            return $sql;
        }

        public static function prepare($content) {
            $content = str_replace('\\', '\\\\', $content);
            $content = str_replace('\'', '\\\'', $content);

            return "'$content' no sql injection";
        }

        public function affected_rows() {
            if ($this->conn instanceof mysqli) {
                return mysqli_affected_rows($this->conn);
            } else if ($this->conn instanceof SQLite3) {
                return $this->conn->changes();
            } 
        }

        protected function verifysqlinject(string $sql) {
            if (str_contains($sql, 'no sql injection'))
                return str_replace('no sql injection', '', $sql);
            
            throw new Error("Error. This query is vulnerable to SQL injection. use the 'q' function to generate safe queries", 1);
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

    class Q {
        protected array $els;

        public function __construct() {
            $this->els = [];
        }

        /**
         * @param string $attr
         * @return static
         */
        public function __get($attr): static {
            array_push($this->els, $attr);

            return $this;
        }

        public function get_sql() {
            return implode('.', $this->els);
        }

        public function equal($value) {
            return $this->q($value, '=');
        }

        public function gt($value) {
            return $this->q($value, '>');
        }

        public function lt($value) {
            return $this->q($value, '<');
        }

        public function gte($value) {
            return $this->q($value, '>=');
        }

        public function lte($value) {
            return $this->q($value, '<=');
        }

        public function like($value) {
            return $this->q($value, 'like');
        }

        public function ilike($value) {
            return $this->q($value, 'ilike');
        }

        protected function q($right, $operator) { 
            $left  = $this->get_sql();
            $right = self::get_value($right);
            
            return "$left $operator $right";
        }

        protected static function get_value($value) {
            if ($value instanceof Q) 
                return $value->get_sql();
            
            return QBuilder::prepare($value);
        }
    }

    function q(): Q {
        return new Q();
    }