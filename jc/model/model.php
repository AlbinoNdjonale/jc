<?php
    namespace jc\model;

    use jc\qbuilder\QBuilder;
    use function jc\qbuilder\q;

    use ReflectionClass;
    use DateTime;
    use Exception;

    abstract class Model {
        protected static string $primary_key = "id";
        protected static ?string $table_name;
        protected static array $attrs_hash = [];
        protected array $values_hash = [];

        protected static array $attrs_ignore = ["primary_key", "table_name", "attrs_hash", "attrs_ignore", "values_hash"];

        public function __construct(array $data) {
            $this->init($data);
        }

        protected function init(array $data) {
            $ref = new ReflectionClass($this);

            foreach ($ref->getProperties() as $prop) {
                $name = $prop->getName();

                if (in_array($name, self::$attrs_ignore)) continue;

                if (!array_key_exists($name, $data)) {
                    $this->{$name} = null;
                    continue;
                }

                $type = $prop->getType();
                if (!$type) continue;

                $value     = $data[$name];
                if (!$value) {
                    $this->{$name} = $value===null?null:$value;
                } else {
                    $type_name = $type->getName();

                    $converted = match ($type_name) {
                        'int'      => (int) $value,
                        'float'    => (float) $value,
                        'bool'     => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                        'string'   => (string) $value,
                        'DateTime' => new DateTime($value),
                        default    => throw new Exception("Unsupoted type: $type_name")
                    };

                    $this->{$name} = $converted;
                }

                if (in_array($name, static::$attrs_hash)) {
                    $this->values_hash[$name] = $this->{$name};
                }
            }
        }

        protected static function cond($value): string {
            return q()->{static::$primary_key}->equal($value);
        }

        public static function get($id, QBuilder $db) {
            $data = $db
                ->table(static::$table_name)
                ->select()
                ->where(self::cond($id))
                ->query()
                ->first();

            if ($data)
                return new static($data);

            return null;
        }

        public function save(QBuilder $db) {
            $data = [];

            $ref = new ReflectionClass($this);

            foreach ($ref->getProperties() as $prop) {
                $name = $prop->getName();

                if (in_array($name, self::$attrs_ignore)) continue;

                if ($this->{$name} === null)
                    continue;
                
                if (is_bool($this->{$name})) {
                    $data[$name] = $this->{$name}?'1':'0';
                } else if ($this->{$name} instanceof DateTime) {
                    $data[$name] = $this->{$name}->format('Y-m-d H:i:s');
                } else {
                    if ((in_array($name, static::$attrs_hash)) && ((!$this->{static::$primary_key}) || $this->{$name} !== $this->values_hash[$name])) {
                        $data[$name] = hash_($this->{$name});
                    } else {
                        $data[$name] = $this->{$name};
                    }
                }
            }

            if (!$this->{static::$primary_key}) {
                $db->table(static::$table_name)->insert($data)->execute();
                $this->{static::$primary_key} = $db->last_insert_id();
            } else {
                $db->table(static::$table_name)->update($data)->where(self::cond($this->{static::$primary_key}));
            }

            $register = $db
                ->table(static::$table_name)
                ->select()
                ->where(self::cond($this->{static::$primary_key}))
                ->query()
                ->first();

            $this->init($register);
        }

        public function delete(QBuilder $db) {
            if ($this->{static::$primary_key}) {
                $db->table(static::$table_name)->delete()->where(self::cond($this->{static::$primary_key}))->execute();
                return true;
            }

            return false;
        }
    }

    class User extends Model {
        protected static ?string $table_name = "user";
        protected static array $attrs_hash = ["password"];


        public ?int $id;
        public string $username;
        public string $email;
        public string $password;
        public bool $is_admin;
        public ?bool $is_active;
        public ?string $first_name;
        public ?string $last_name;
        public ?string $gender;
        public ?DateTime $birth;
        public ?DateTime $date_joined;
    }
