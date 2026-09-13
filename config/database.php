<?php
declare(strict_types=1);

// ============================================================
// Database Configuration
// ============================================================

class Database
{
    private static ?PDO $instance = null;
    private static array $settings = [];

    public static function init(array $settings): void
    {
        self::$settings = $settings;
    }

    private static function env(string $key, ?string $default = null): ?string {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return $value !== false && $value !== null && $value !== '' ? (string)$value : $default;
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            if (empty(self::$settings)) {
                $dbUrl = self::env('DATABASE_URL');

                if ($dbUrl !== null) {
                    $url = parse_url($dbUrl);
                    $dbname = ltrim((string)($url['path'] ?? ''), '/');
                    $driver = str_starts_with($dbUrl, 'pgsql://') || str_starts_with($dbUrl, 'postgresql://') ? 'pgsql' : 'mysql';
                    $charset = $driver === 'pgsql' ? 'utf8' : 'utf8mb4';
                    self::$settings = [
                        'driver'   => $driver,
                        'host'     => (string)($url['host'] ?? 'localhost'),
                        'port'     => (int)($url['port'] ?? ($driver === 'pgsql' ? 5432 : 3306)),
                        'dbname'   => $dbname ?: ($driver === 'pgsql' ? 'ipihrs_chc' : 'ipihrs_chc'),
                        'username' => (string)($url['user'] ?? 'root'),
                        'password' => (string)($url['pass'] ?? ''),
                        'charset'  => $charset,
                    ];
                } else {
                    $dbUrlFromParts = self::env('DATABASE_URL');
                    $driver = 'mysql';
                    if ($dbUrlFromParts !== null) {
                        $url = parse_url($dbUrlFromParts);
                        $driver = str_starts_with($dbUrlFromParts, 'pgsql://') || str_starts_with($dbUrlFromParts, 'postgresql://') ? 'pgsql' : 'mysql';
                    }

                    self::$settings = [
                        'driver'   => $driver,
                        'host'     => self::env('DB_HOST', 'localhost') ?? 'localhost',
                        'port'     => (int) (self::env('DB_PORT') ?: ($driver === 'pgsql' ? 5432 : 3306)),
                        'dbname'   => self::env('DB_NAME', 'ipihrs_chc') ?? 'ipihrs_chc',
                        'username' => self::env('DB_USER', 'root') ?? 'root',
                        'password' => self::env('DB_PASSWORD', '') ?? '',
                        'charset'  => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
                    ];
                }
            }

            $driver = self::$settings['driver'] ?? 'mysql';
            if ($driver === 'pgsql') {
                $host = self::$settings['host'];
                $port = (int)self::$settings['port'];
                $dbname = self::$settings['dbname'];
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    self::$settings['host'],
                    self::$settings['dbname'],
                    self::$settings['charset']
                );

                if (!empty(self::$settings['port'])) {
                    $dsn .= ';port=' . (int)self::$settings['port'];
                }
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, self::$settings['username'], self::$settings['password'], $options);
            } catch (PDOException $e) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage());
            } catch (\Throwable $e) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function getInstance(): PDO
    {
        return self::getConnection();
    }
}
