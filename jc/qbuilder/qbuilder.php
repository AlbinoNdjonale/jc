<?php

    namespace jc\qbuilder;

    use mysqli;
    use mysqli_result;
    use SQLite3;
    use SQLite3Result;
    use PgSql\Connection as PostgreSql;
    use PgSql\Result as PostgreSqlResult;
    use Error;

    define('NOERRORSQL', 'no sql injection');

    class QBuilder {
        protected mysqli|SQLite3|PostgreSql|null $conn;
        protected string $dbconnection;
        protected array $lines = [];
        protected array $sqls = [];

        protected string $table = '';
        protected string $start = '';
        protected array $wheres = [];
        protected array $ons = [];
        protected ?int $limit_ = null;
        protected int $offset_ = 0;
        protected ?string $order_by_ = null;
        protected bool $desc_ = false;

        protected bool $only_start = false;
        protected ?PostgreSqlResult $pg_result;

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

            $this->start = "SELECT $attrs FROM {$this->table}";
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

            $this->start = "INSERT INTO {$this->table}($keys) VALUES ($values)";
            return $this;
        }

        public function update(array $data) {
            $values = [];
            foreach ($data as $key => $value) {
                array_push($values, "`$key` = ".$this->verifysqlinject(self::prepare($value)));
            }
            $values = implode(',', $values);

            $this->start = "UPDATE {$this->table} SET {$values}";

            return $this;
        }

        public function delete() {
            $this->start = "DELETE FROM {$this->table}";

            return $this;
        }

        protected function attributes(array $attrs) {
            return implode(",\n", array_map(
                fn($key, $value) => (
                    "`$key` ".((in_array('auto_increment', $value)&&$this->dbconnection==='postgresql')?"SERIAL":$value[0])
                    .(in_array('not_null', $value)?' NOT NULL':'')
                    .(in_array('auto_increment', $value)&&$this->dbconnection==='mysql'?" AUTO_INCREMENT":"")
                    .(isset($value['default'])?" DEFAULT {$value['default']}":'')
                    .(in_array('unique', $value)&&$this->dbconnection==='sqlite'?" UNIQUE":"")
                ),
                array_keys($attrs),
                $attrs
            ));
        }

        public function create(array $attrs) {
            $foreign_keys = array_filter($attrs, fn ($attr) => in_array('foreign_key', $attr));
            
            $foreign_keys = implode(",\n", array_map(
                fn($key, $value) => "FOREIGN KEY ($key) REFERENCES {$value['reference']}".(isset($value['on_delete'])?" ON DELETE {$value['on_delete']}":''),
                array_keys($foreign_keys), $foreign_keys
            ));

            $uniques = "";
            if ($this->dbconnection == "sqlite") {
                $uniques = array_filter($attrs, fn ($attr) => in_array('unique', $attr));
            
                $uniques = implode(",\n", array_map(
                    fn($key) => "UNIQUE ($key)",
                    array_keys($uniques)
                ));
            }

            $primary_key = "";
            foreach ($attrs as $key => $value) {
                if (in_array('primary_key', $value)) {
                    $autoincrement = in_array('auto_increment', $value)&&$this->dbconnection==='sqlite'?" AUTOINCREMENT":"";
                    $primary_key = "PRIMARY KEY ($key$autoincrement)";
                    
                    break;
                }
            }
            
            $attrs = $this->attributes($attrs);

            $attrs = implode(",\n", array_filter([$attrs, $foreign_keys, $uniques, $primary_key], fn($attr) => $attr != ""));
            
            $this->start = "CREATE TABLE IF NOT EXISTS {$this->table} (
                $attrs
            )";

            $this->only_start = true;

            return $this;
        }

        public function drop() {
            $this->start = "DROP TABLE {$this->table}";

            $this->only_start = true;

            return $this;
        }

        public function drop_column(string $column) {
            $this->start = "ALTER TABLE {$this->table} DROP $column";

            $this->only_start = true;

            return $this;
        }

        public function add_column(string $column, array $attr) {
            $attr = $this->attributes([$column => $attr]);
            $this->start = "ALTER TABLE {$this->table} ADD COLUMN $attr";

            $this->only_start = true;

            return $this;
        }

        public function change_column(string $column, array $attr, ?string $new_column = null) {
            if ($this->dbconnection === "mysql") {
                $attr = $this->attributes([$new_column??$column => $attr]);
                $this->start = "ALTER TABLE {$this->table} CHANGE $column $attr";
            } else {
                $sqls = [];

                if ($this->dbconnection === "postgresql" and count($attr) > 0) {
                    array_push($sqls, "ALTER TABLE {$this->table} ALTER COLUMN $column TYPE {$attr[0]} USING $column::{$attr[0]}");

                    if (isset($attr['default'])) {
                        array_push($sqls, "ALTER TABLE {$this->table} ALTER COLUMN $column SET DEFAULT {$attr['default']}");
                    } else {
                        array_push($sqls, "ALTER TABLE {$this->table} ALTER COLUMN $column DROP DEFAULT");
                    }

                    if (in_array('not_null', $attr)) {
                        array_push($sqls, "ALTER TABLE {$this->table} ALTER COLUMN $column SET NOT NULL");
                    } else {
                        array_push($sqls, "ALTER TABLE {$this->table} ALTER COLUMN $column DROP NOT NULL");
                    }
                }

                if ($new_column) {
                    array_push($sqls, "ALTER TABLE {$this->table} RENAME COLUMN $column TO $new_column");
                }

                $this->start = implode(";\n", $sqls);
            }

            $this->only_start = true;
            
            return $this;
        }

        public function add_foreign_key(string $column, string $reference, ?string $on_delete = null) {
            $on_delete = $on_delete?" ON DELETE $on_delete":"";
            $name_constraint = "fk_{$column}_".str_replace(['(', ')'], ['_', ''], $reference);
            $this->start = "ALTER TABLE {$this->table} ADD CONSTRAINT $name_constraint FOREIGN KEY ($column) REFERENCES $reference$on_delete";

            $this->only_start = true;
            
            return $this;
        }

        public function rename(string $new_name) {
            $this->start = "ALTER TABLE {$this->table} RENAME TO $new_name";
            
            $this->only_start = true;

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

        public function order_by(string $column) {
            $this->order_by_ = $column;

            return $this;
        }

        public function desc() {
            $this->desc_ = true;

            return $this;
        }

        public function execute(string|null $sql_ = null) {
            $sql = $sql_??$this->get_query();

            if ($this->conn instanceof mysqli) {
                $res = $this->conn->prepare($sql);
                return $res->execute();
            } else if ($this->conn instanceof SQLite3) {
                return $this->conn->exec($sql);
            } else if ($this->conn instanceof PostgreSql) {
                $this->pg_result = pg_query($this->conn, $sql);
                return $this->pg_result !== false;
            }
        }

        public function save_sql() {
            $sql = $this->get_query();
            array_push($this->sqls, "$sql;");
        }

        public function execute_all() {
            $sql = implode("\n", $this->sqls);
            $this->sqls = [];
            return $this->execute($sql);
        }

        public function query() {
            $query = $this->get_query();

            if ($this->conn instanceof mysqli) {
                $result = mysqli_query($this->conn, $query);                
            } else if ($this->conn instanceof SQLite3) {
                $result = $this->conn->query($query);
            } else if ($this->conn instanceof PostgreSql) {
                $result = pg_query($this->conn, $query);
                $this->pg_result = $result;
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
            return $this->lines[0]??null;
        }

        public function value() {
            return array_values($this->lines[0])[0];
        }

        public function exist() {
            if (count($this->lines) > 0) return true;
            return false;
        }

        protected function fetch_array(SQLite3Result | mysqli_result | PostgreSqlResult $result) {
            if ($result instanceof mysqli_result) {
                return mysqli_fetch_assoc($result);
            } else if ($result instanceof SQLite3Result) {
                return $result->fetchArray(SQLITE3_ASSOC);
            } if ($result instanceof PostgreSqlResult) {
                return pg_fetch_assoc($result);
            }

            return null;
        }

        public function get_query() {
            $wheres = count($this->wheres) > 0?" WHERE ".implode(' ', $this->wheres):'';

            $ons = count($this->ons) > 0?" ON ".implode(' ON ', $this->ons):'';

            $limit  = $this->limit_?" LIMIT {$this->limit_}":'';
            $offset = $this->limit_?" OFFSET {$this->offset_}":'';

            $order_by = $this->order_by_?" ORDER BY {$this->order_by_}":'';
            $desc     = $this->desc_?" DESC":'';

            if ($this->only_start)
                $sql = $this->start;
            else {
                $sql = $this->start == 'exists'?"SELECT EXISTS(SELECT 1 FROM {$this->table}$wheres{$limit}{$offset}{$ons})":"{$this->start}$wheres$order_by$desc$limit$offset$ons";
            }

            $this->table      = '';
            $this->start      = '';
            $this->wheres     = [];
            $this->ons        = [];
            $this->offset_    = 0;
            $this->limit_     = null;
            $this->order_by_  = null;
            $this->desc_      = false;
            $this->only_start = false;

            return $sql;
        }

        public static function prepare($content) {
            $content = str_replace('\\', '\\\\', $content);
            $content = str_replace('\'', '\\\'', $content);

            $noerrorsql = NOERRORSQL;

            return "'$content' {$noerrorsql}";
        }

        public function last_insert_id() {
            if ($this->conn instanceof mysqli) {
                return mysqli_insert_id($this->conn);
            } else if ($this->conn instanceof SQLite3) {
                return $this->conn->lastInsertRowID();
            } else if ($this->conn instanceof PostgreSql) {
                return pg_last_oid($this->pg_result);
            }
        }

        public function affected_rows() {
            if ($this->conn instanceof mysqli) {
                return mysqli_affected_rows($this->conn);
            } else if ($this->conn instanceof SQLite3) {
                return $this->conn->changes();
            } else if ($this->conn instanceof PostgreSql) {
                return pg_affected_rows($this->pg_result);
            }
        }

        public function get_error() {
            if ($this->conn instanceof mysqli) {
                return [
                    "code" => mysqli_errno($this->conn),
                    "msg"  => mysqli_error($this->conn)
                ];
            } else if ($this->conn instanceof SQLite3) {
                return [
                    "code" => $this->conn->lastErrorCode(),
                    "msg"  => $this->conn->lastErrorMsg()
                ];
            } else if ($this->conn instanceof PostgreSql) {
                return [
                    "code" => null,
                    "msg"  => pg_last_error($this->conn)
                ];
            }
        }

        protected function verifysqlinject(string $sql) {
            if (str_contains($sql, NOERRORSQL))
                return str_replace(NOERRORSQL, '', $sql);
            
            throw new Error("Error. This query is vulnerable to SQL injection. use the 'q' function to generate safe queries", 1);
        }

        protected function connect(string $dbname, ?string $dbpassword = null, ?string $dbhost = null, ?string $dbuser = null, ?int $dbport = null) {
            $conn = NULL;

            try {
                $conn = match ($this->dbconnection) {
                    'mysql' => new mysqli(
                        $dbhost,
                        $dbuser,
                        $dbpassword,
                        $dbname,
                        $dbport
                    ),
                    'sqlite' => new SQLite3($dbname),
                    'postgresql' => pg_connect("host=$dbhost dbname=$dbname user=$dbuser password=$dbpassword")
                };
            } catch (\Throwable $th) {
                throw $th;
            }

            return $conn;
        }

        public function close() {
            if ($this->conn instanceof mysqli) {
                mysqli_close($this->conn);
            } else if ($this->conn instanceof SQLite3) {
                $this->conn->close();
            } else if ($this->conn instanceof PostgreSql) {
                pg_close($this->conn);
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