<?php
class Database {
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct() {
        $host    = $_ENV['DB_HOST'] ?? 'localhost';
        $db      = $_ENV['DB_NAME'] ?? 'ftth_network';
        $user    = $_ENV['DB_USER'] ?? 'root';
        $pass    = $_ENV['DB_PASS'] ?? '';
        $charset = 'utf8mb4';

        $this->pdo = new PDO(
            "mysql:host=$host;dbname=$db;charset=$charset",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    public static function getInstance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function getPdo(): PDO { return $this->pdo; }

    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array {
        return $this->query($sql, $params)->fetch() ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): int {
        $cols = implode(', ', array_keys($data));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO $table ($cols) VALUES ($ph)", array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set  = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $stmt = $this->query("UPDATE $table SET $set WHERE $where", [...array_values($data), ...$whereParams]);
        return $stmt->rowCount();
    }
}
