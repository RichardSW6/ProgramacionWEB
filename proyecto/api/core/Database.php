<?php
declare(strict_types=1);

class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $host    = getenv('DB_HOST')    ?: 'localhost';
            $port    = getenv('DB_PORT')    ?: '3306';
            $dbname  = getenv('DB_NAME')    ?: 'gastos_empresariales';
            $user    = getenv('DB_USER')    ?: 'root';
            $pass    = getenv('DB_PASS')    ?: '';
            $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            try {
                $this->connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (\PDOException $e) {
                http_response_code(503);
                echo json_encode([
                    'success' => false,
                    'message' => 'No se puede conectar a la base de datos.',
                    'data'    => null,
                    'errors'  => [
                        getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Servicio no disponible.'
                    ],
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        return $this->connection;
    }
}
