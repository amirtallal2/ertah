<?php
/**
 * فئة الاتصال بقاعدة البيانات
 * Database Connection Class
 */

require_once __DIR__ . '/../config/config.php';

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Keep MySQL session timezone aligned with PHP timezone to avoid
            // relative-time drift in admin screens (e.g. "منذ ساعة" مباشرة بعد الإرسال).
            try {
                $timezoneOffset = date('P'); // +03:00
                $this->connection->exec("SET time_zone = " . $this->connection->quote($timezoneOffset));
            } catch (Throwable $ignored) {
                // Non-fatal: continue even if session timezone cannot be set.
            }
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }

    public function insert(string $table, array $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);

        return $this->connection->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = [])
    {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $setString = implode(', ', $set);

        // Check if whereParams is associative (named) or sequential (positional)
        $isAssociative = count($whereParams) > 0 && array_keys($whereParams) !== range(0, count($whereParams) - 1);

        if ($isAssociative) {
            // Already using named params, just merge
            $finalParams = array_merge($data, $whereParams);
        } else {
            // Convert positional ? to named params
            $namedWhereParams = [];
            $paramIndex = 0;
            $where = preg_replace_callback('/\?/', function ($match) use (&$whereParams, &$namedWhereParams, &$paramIndex) {
                $paramName = 'where_param_' . $paramIndex;
                $namedWhereParams[$paramName] = $whereParams[$paramIndex] ?? null;
                $paramIndex++;
                return ':' . $paramName;
            }, $where);
            $finalParams = array_merge($data, $namedWhereParams);
        }

        $sql = "UPDATE {$table} SET {$setString} WHERE {$where}";
        return $this->query($sql, $finalParams)->rowCount();
    }

    public function delete(string $table, string $where, array $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
    }

    public function count(string $table, string $where = '1=1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
        $result = $this->fetch($sql, $params);
        return (int) ($result['count'] ?? 0);
    }

    private function __clone()
    {
    }
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

// دالة مساعدة للحصول على اتصال قاعدة البيانات
function db(): Database
{
    return Database::getInstance();
}
