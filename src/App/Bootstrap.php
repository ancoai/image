<?php

namespace App;

use PDO;

class Bootstrap
{
    private Database $database;
    private Auth $auth;
    private StorageManager $storageManager;
    private ImageService $imageService;
    private VerificationService $verificationService;

    public function __construct(private array $config)
    {
        $this->database = new Database($config['database']);
        $pdo = $this->database->pdo();
        $this->ensureAdminExists($pdo);
        $this->storageManager = new StorageManager($pdo);
        $this->storageManager->ensureDefaultLocal();
        $this->auth = new Auth($pdo);
        $this->imageService = new ImageService($pdo, $this->storageManager);
        $this->verificationService = new VerificationService($pdo);
    }

    public function pdo(): PDO
    {
        return $this->database->pdo();
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    public function storageManager(): StorageManager
    {
        return $this->storageManager;
    }

    public function imageService(): ImageService
    {
        return $this->imageService;
    }

    public function verificationService(): VerificationService
    {
        return $this->verificationService;
    }

    private function ensureAdminExists(PDO $pdo): void
    {
        $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM users');
        $count = (int)$stmt->fetch()['cnt'];
        if ($count === 0) {
            $insert = $pdo->prepare('INSERT INTO users (username, password_hash, is_admin, created_at) VALUES (:username, :password_hash, 1, :created_at)');
            $insert->execute([
                ':username' => 'admin',
                ':password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                ':created_at' => date('c'),
            ]);
        }
    }
}
