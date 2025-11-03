<?php

namespace App;

use PDO;
use RuntimeException;

class StorageManager
{
    public function __construct(private PDO $pdo)
    {
    }

    public function ensureStorageConfigDefaults(string $type, array &$config): void
    {
        if ($type === 'local') {
            $path = rtrim($config['path'] ?? (__DIR__ . '/../../storage/local'), '/');
            if (!is_dir($path)) {
                if (!mkdir($path, 0775, true) && !is_dir($path)) {
                    throw new RuntimeException('无法创建本地图库目录：' . $path);
                }
            }
            $config['path'] = $path;
            if (empty($config['public_url'])) {
                $config['public_url'] = '/storage/local';
            }
            $this->ensurePublicBridge($config['public_url']);
        } elseif ($type === 'r2') {
            if (empty($config['endpoint']) && !empty($config['account_id'])) {
                $config['endpoint'] = sprintf('https://%s.r2.cloudflarestorage.com', $config['account_id']);
            }
            if (empty($config['region'])) {
                $config['region'] = 'auto';
            }
        }
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
            $config = ['path' => __DIR__ . '/../../storage/local', 'public_url' => '/storage/local'];
            $this->ensureStorageConfigDefaults('local', $config);
            $insert = $this->pdo->prepare('INSERT INTO storage_configs (name, type, is_default, config_json, created_at) VALUES (:name, :type, 1, :config, :created_at)');
            $insert->execute([
                ':name' => '本地图库',
                ':type' => 'local',
                ':config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                ':created_at' => date('c'),
            ]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM storage_configs WHERE type = 'local'");
            foreach ($stmt->fetchAll() as $storage) {
                $config = json_decode((string)$storage['config_json'], true) ?? [];
                $this->ensureStorageConfigDefaults('local', $config);
                $encoded = json_encode($config, JSON_UNESCAPED_UNICODE);
                if ($encoded !== (string)$storage['config_json']) {
                    $update = $this->pdo->prepare('UPDATE storage_configs SET config_json = :config WHERE id = :id');
                    $update->execute([
                        ':config' => $encoded,
                        ':id' => (int)$storage['id'],
                    ]);
                }
            }
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

    private function ensurePublicBridge(string $publicUrl): void
    {
        if (!str_starts_with($publicUrl, '/')) {
            return;
        }
        if (!str_starts_with($publicUrl, '/storage')) {
            return;
        }
        $publicDir = __DIR__ . '/../../public/storage';
        if (!is_dir($publicDir) && !is_link($publicDir)) {
            $target = '../storage';
            if (function_exists('symlink')) {
                @symlink($target, $publicDir);
            }
            if (!is_dir($publicDir)) {
                if (!mkdir($publicDir, 0775, true) && !is_dir($publicDir)) {
                    throw new RuntimeException('无法创建公开图库目录：' . $publicDir);
                }
            }
        }
    }

    public function publicUrlFor(array $storage, string $filename): string
    {
        $config = json_decode((string)$storage['config_json'], true) ?? [];
        if ($storage['type'] === 'local') {
            $base = rtrim($config['public_url'] ?? '/storage/local', '/');
            if ($this->isPublicFileAvailable($base, $filename)) {
                return $base . '/' . rawurlencode($filename);
            }
            return '/index.php?route=media&storage=' . (int)$storage['id'] . '&file=' . rawurlencode($filename);
        }

        if ($storage['type'] === 'r2') {
            $publicBase = rtrim($config['public_base'] ?? ($config['endpoint'] ?? ''), '/');
            if ($publicBase !== '') {
                return $publicBase . '/' . rawurlencode($filename);
            }
        }

        return '/index.php?route=media&storage=' . (int)$storage['id'] . '&file=' . rawurlencode($filename);
    }

    private function isPublicFileAvailable(string $base, string $filename): bool
    {
        if ($base === '') {
            return false;
        }
        if (!str_starts_with($base, '/')) {
            return true;
        }
        $publicRoot = realpath(__DIR__ . '/../../public');
        if ($publicRoot === false) {
            return false;
        }
        $full = $publicRoot . $base . '/' . $filename;
        return is_file($full);
    }
}
