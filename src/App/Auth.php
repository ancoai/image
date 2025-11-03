<?php

namespace App;

use PDO;

class Auth
{
    public function __construct(private PDO $pdo)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function requireAdmin(): void
    {
        $user = $this->user();
        if (!$user || !(int)$user['is_admin']) {
            header('Location: /index.php?route=login');
            exit;
        }
    }

    public function loginWithPassword(string $username, string $password): bool
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        if (!$user) {
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        $_SESSION['user_id'] = $user['id'];
        return true;
    }

    public function loginWithOAuth(string $provider, string $subject, string $displayName): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE oauth_provider = :provider AND oauth_subject = :subject');
        $stmt->execute([':provider' => $provider, ':subject' => $subject]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            return $user;
        }

        $stmt = $this->pdo->prepare('INSERT INTO users (username, password_hash, is_admin, oauth_provider, oauth_subject, created_at) VALUES (:username, NULL, 0, :provider, :subject, :created_at)');
        $username = $displayName ?: ($provider . '-' . substr($subject, -6));
        $stmt->execute([
            ':username' => $username,
            ':provider' => $provider,
            ':subject' => $subject,
            ':created_at' => date('c'),
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $_SESSION['user_id'] = $id;

        return $this->user();
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
