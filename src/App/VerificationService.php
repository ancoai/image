<?php

namespace App;

use PDO;

class VerificationService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getOrCreateToken(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM verification_tokens WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        $token = $stmt->fetch();
        if ($token) {
            return $token;
        }

        $tokenValue = bin2hex(random_bytes(16));
        $htmlSnippet = '<div class="puzzle-verify" data-token="' . $tokenValue . '"></div>';
        $stmt = $this->pdo->prepare('INSERT INTO verification_tokens (user_id, token, html_snippet, created_at) VALUES (:user_id, :token, :html_snippet, :created_at)');
        $stmt->execute([
            ':user_id' => $userId,
            ':token' => $tokenValue,
            ':html_snippet' => $htmlSnippet,
            ':created_at' => date('c'),
        ]);

        return $this->getOrCreateToken($userId);
    }

    public function recordVerification(string $token, bool $success): bool
    public function recordVerification(string $token, bool $success): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM verification_tokens WHERE token = :token');
        $stmt->execute([':token' => $token]);
        $record = $stmt->fetch();
        if (!$record) {
            return false;
            return;
        }

        $update = $this->pdo->prepare('UPDATE verification_tokens SET success_count = success_count + :delta, last_verified_at = :last WHERE id = :id');
        $update->execute([
            ':delta' => $success ? 1 : 0,
            ':last' => date('c'),
            ':id' => $record['id'],
        ]);

        $log = $this->pdo->prepare('INSERT INTO verification_logs (token_id, success, created_at) VALUES (:token_id, :success, :created_at)');
        $log->execute([
            ':token_id' => $record['id'],
            ':success' => $success ? 1 : 0,
            ':created_at' => date('c'),
        ]);

        return true;
    }

    public function overview(): array
    {
        $stmt = $this->pdo->query('SELECT v.*, u.username FROM verification_tokens v JOIN users u ON v.user_id = u.id');
        $tokens = $stmt->fetchAll();
        foreach ($tokens as &$token) {
            $logStmt = $this->pdo->prepare('SELECT COUNT(*) AS total, SUM(success) AS successes FROM verification_logs WHERE token_id = :token_id');
            $logStmt->execute([':token_id' => $token['id']]);
            $stats = $logStmt->fetch();
            $token['total_attempts'] = (int)($stats['total'] ?? 0);
            $token['successes'] = (int)($stats['successes'] ?? 0);
        }
        return $tokens;
    }
}
