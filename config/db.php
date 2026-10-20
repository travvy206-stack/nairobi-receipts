<?php
/**
 * Singleton Database Connection Handler
 * Supports SQLite and MySQL configured via .env
 */

class Database {
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private array $env = [];

    private function __construct() {
        $this->loadEnv();
        $this->connect();
    }

    private function loadEnv(): void {
        $envPath = dirname(__DIR__) . '/.env';
        if (!file_exists($envPath)) {
            $envPath = dirname(__DIR__) . '/.env.example';
        }

        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    $this->env[$key] = $value;
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }
    }

    public function getEnv(string $key, ?string $default = null): ?string {
        return $this->env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    private function connect(): void {
        $driver = strtolower($this->getEnv('DB_CONNECTION', 'sqlite'));

        try {
            if ($driver === 'sqlite') {
                $dbFile = $this->getEnv('DB_DATABASE', 'db/nairobi_traders.sqlite');
                if (!str_starts_with($dbFile, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $dbFile)) {
                    $dbFile = dirname(__DIR__) . '/' . ltrim($dbFile, '/\\');
                }

                $dbDir = dirname($dbFile);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0777, true);
                }

                $this->connection = new PDO("sqlite:" . $dbFile, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $this->connection->exec("PRAGMA foreign_keys = ON;");
            } elseif ($driver === 'mysql') {
                $host = $this->getEnv('DB_HOST', '127.0.0.1');
                $port = $this->getEnv('DB_PORT', '3306');
                $dbname = $this->getEnv('DB_DATABASE', 'nairobi_traders');
                $user = $this->getEnv('DB_USERNAME', 'root');
                $pass = $this->getEnv('DB_PASSWORD', '');

                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
                $this->connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } else {
                throw new InvalidArgumentException("Unsupported database driver: $driver");
            }
        } catch (PDOException $e) {
            die(json_encode([
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]));
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
