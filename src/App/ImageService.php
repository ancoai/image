<?php

namespace App;

use PDO;
use RuntimeException;

class ImageService
{
    public function __construct(private PDO $pdo, private StorageManager $storageManager)
    {
    }

    public function createFromUpload(array $file, int $storageId, int $userId, string $title, string $visibility, int $cols, int $rows): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('上传失败');
        }

        $storage = $this->storageManager->get($storageId);
        if (!$storage) {
            throw new RuntimeException('图库不存在');
        }

        $config = json_decode((string)$storage['config_json'], true) ?? [];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            throw new RuntimeException('仅支持图片格式');
        }

        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new RuntimeException('无法读取图片信息');
        }

        $filename = uniqid('img_', true) . '.' . $ext;
        $relativePath = $filename;

        if ($storage['type'] === 'local') {
            $path = rtrim($config['path'] ?? (__DIR__ . '/../../storage/local'), '/');
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }
            $destination = $path . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('保存图片失败');
            }
            $publicUrl = rtrim($config['public_url'] ?? '/storage/local', '/') . '/' . $filename;
        } elseif ($storage['type'] === 'r2') {
            $publicUrl = $this->uploadToR2($file['tmp_name'], $filename, $config);
        } else {
            throw new RuntimeException('未知图库类型');
        }

        $stmt = $this->pdo->prepare('INSERT INTO images (storage_id, user_id, filename, original_name, width, height, created_at, public_url) VALUES (:storage_id, :user_id, :filename, :original_name, :width, :height, :created_at, :public_url)');
        $stmt->execute([
            ':storage_id' => $storageId,
            ':user_id' => $userId,
            ':filename' => $relativePath,
            ':original_name' => $file['name'],
            ':width' => $imageInfo[0],
            ':height' => $imageInfo[1],
            ':created_at' => date('c'),
            ':public_url' => $publicUrl,
        ]);
        $imageId = (int)$this->pdo->lastInsertId();

        $slug = bin2hex(random_bytes(8));
        $stmt = $this->pdo->prepare('INSERT INTO puzzles (image_id, slug, title, visibility, grid_cols, grid_rows, created_at, created_by) VALUES (:image_id, :slug, :title, :visibility, :grid_cols, :grid_rows, :created_at, :created_by)');
        $stmt->execute([
            ':image_id' => $imageId,
            ':slug' => $slug,
            ':title' => $title,
            ':visibility' => $visibility,
            ':grid_cols' => $cols,
            ':grid_rows' => $rows,
            ':created_at' => date('c'),
            ':created_by' => $userId,
        ]);

        return $this->getPuzzleBySlug($slug);
    }

    public function getPuzzleBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, i.public_url, i.width, i.height FROM puzzles p JOIN images i ON p.image_id = i.id WHERE p.slug = :slug');
        $stmt->execute([':slug' => $slug]);
        $puzzle = $stmt->fetch();
        return $puzzle ?: null;
    }

    public function listPuzzles(?int $userId = null): array
    {
        if ($userId) {
            $stmt = $this->pdo->prepare('SELECT p.*, i.public_url FROM puzzles p JOIN images i ON p.image_id = i.id WHERE p.created_by = :user_id ORDER BY p.created_at DESC');
            $stmt->execute([':user_id' => $userId]);
        } else {
            $stmt = $this->pdo->query("SELECT p.*, i.public_url FROM puzzles p JOIN images i ON p.image_id = i.id WHERE p.visibility = 'public' ORDER BY p.created_at DESC");
        }
        return $stmt->fetchAll();
    }

    private function uploadToR2(string $tmpFile, string $filename, array $config): string
    {
        foreach (['account_id', 'access_key', 'secret_key', 'bucket', 'endpoint'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException('R2 配置缺失: ' . $key);
            }
        }

        $region = $config['region'] ?? 'auto';
        $endpoint = rtrim($config['endpoint'], '/');
        $bucket = $config['bucket'];
        $urlPath = '/' . $bucket . '/' . $filename;
        $host = parse_url($endpoint, PHP_URL_HOST) ?: $endpoint;
        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $content = file_get_contents($tmpFile);
        $hash = hash('sha256', $content);
        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $hash,
            'x-amz-date' => $now,
        ];

        $canonicalHeaders = '';
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
        }
        $signedHeaders = implode(';', array_map('strtolower', array_keys($headers)));

        $canonicalRequest = implode("\n", [
            'PUT',
            $urlPath,
            '',
            $canonicalHeaders,
            $signedHeaders,
            $hash,
        ]);

        $scope = $date . '/' . $region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $now,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $kSecret = 'AWS4' . $config['secret_key'];
        $kDate = hash_hmac('sha256', $date, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $config['access_key'],
            $scope,
            $signedHeaders,
            $signature
        );

        $headersOut = [
            'Authorization: ' . $authorization,
            'x-amz-content-sha256: ' . $hash,
            'x-amz-date: ' . $now,
            'Content-Length: ' . strlen($content),
        ];

        $ch = curl_init($endpoint . $urlPath);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headersOut);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($result === false || $status >= 400) {
            $error = curl_error($ch) ?: $result;
            curl_close($ch);
            throw new RuntimeException('上传到 R2 失败: ' . $error);
        }
        curl_close($ch);

        $publicBase = $config['public_base'] ?? ($endpoint . '/' . $bucket);
        return rtrim($publicBase, '/') . '/' . $filename;
    }
}
