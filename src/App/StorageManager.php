<?php

namespace App;

use PDO;
use RuntimeException;

class StorageManager
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM storage_configs ORDER BY is_default DESC, name');
        return $stmt->fetchAll();
    }

    public function defaultStorage(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM storage_configs WHERE is_default = 1 LIMIT 1');
        $storage = $stmt->fetch();
        if (!$storage) {
            throw new RuntimeException('No default storage configured');
        }
        return $storage;
    }

    public function get(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM storage_configs WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $storage = $stmt->fetch();
        return $storage ?: null;
    }

    public function ensureDefaultLocal(): void
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS cnt FROM storage_configs');
        $count = (int)$stmt->fetch()['cnt'];
        if ($count === 0) {
            $insert = $this->pdo->prepare('INSERT INTO storage_configs (name, type, is_default, config_json, created_at) VALUES (:name, :type, 1, :config, :created_at)');
            $insert->execute([
                ':name' => '本地图库',
                ':type' => 'local',
                ':config' => json_encode(['path' => __DIR__ . '/../../storage/local', 'public_url' => '/storage/local']),
                ':created_at' => date('c'),
            ]);
        }
    }

    public function setDefault(int $id): void
    {
        $this->pdo->exec('UPDATE storage_configs SET is_default = 0');
        $stmt = $this->pdo->prepare('UPDATE storage_configs SET is_default = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function save(string $name, string $type, array $config, bool $isDefault, ?int $id = null): void
    {
        $payload = [
            ':name' => $name,
            ':type' => $type,
            ':config' => json_encode($config, JSON_UNESCAPED_UNICODE),
            ':created_at' => date('c'),
        ];

        if ($id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO storage_configs (name, type, is_default, config_json, created_at) VALUES (:name, :type, :is_default, :config, :created_at)');
        } else {
            $stmt = $this->pdo->prepare('UPDATE storage_configs SET name = :name, type = :type, is_default = :is_default, config_json = :config WHERE id = :id');
            $payload[':id'] = $id;
        }

        $payload[':is_default'] = $isDefault ? 1 : 0;
        $stmt->execute($payload);

        if ($isDefault) {
            if ($id === null) {
                $id = (int)$this->pdo->lastInsertId();
            }
            $this->setDefault($id);
        }
    }
}
