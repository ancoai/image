<?php

namespace App;

use PDO;

class OAuthProviderService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM oauth_providers ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function createOrUpdate(?int $id, string $name, string $displayName, array $config): void
    {
        $payload = [
            ':name' => $name,
            ':display_name' => $displayName,
            ':config_json' => json_encode($config, JSON_UNESCAPED_UNICODE),
            ':created_at' => date('c'),
        ];

        if ($id) {
            $stmt = $this->pdo->prepare('UPDATE oauth_providers SET name = :name, display_name = :display_name, config_json = :config_json WHERE id = :id');
            $payload[':id'] = $id;
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO oauth_providers (name, display_name, config_json, created_at) VALUES (:name, :display_name, :config_json, :created_at)');
        }

        $stmt->execute($payload);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM oauth_providers WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
