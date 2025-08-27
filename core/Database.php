<?php
declare(strict_types=1);

final class Database {
    private static ?Database $self = null;
    private \PDO $pdo;

    private function __construct(array $cfg) {
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4";
        $opt = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = new \PDO($dsn, $cfg['user'], $cfg['pass'], $opt);
    }

    public static function instance(array $cfg = []): Database {
        if (self::$self === null) {
            self::$self = new self($cfg);
        }
        return self::$self;
    }

    public function pdo(): \PDO {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): \PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function insert(string $sql, array $params = []): int {
        $this->query($sql, $params);
        return (int)$this->pdo->lastInsertId();
    }
}
