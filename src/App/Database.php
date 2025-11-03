<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private string $path;
    private ?PDO $pdo = null;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->initialize();
        }

        return $this->pdo;
    }

    private function initialize(): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $needMigrations = !file_exists($this->path);
        $dsn = 'sqlite:' . $this->path;
        $this->pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        if ($needMigrations) {
            $this->runMigrations();
        }
    }

    private function runMigrations(): void
    {
        $schemaSql = file_get_contents(__DIR__ . '/../../database/schema.sql');
        if ($schemaSql === false) {
            throw new PDOException('Unable to load schema.sql');
        }

        $this->pdo->exec($schemaSql);
    }
}
