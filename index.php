<?php

declare(strict_types=1);

use App\Bootstrap;
use App\OAuthProviderService;
use App\VerificationService;

require __DIR__ . '/autoload.php';

$config = require __DIR__ . '/config.php';
$bootstrap = new Bootstrap($config);
$auth = $bootstrap->auth();
$pdo = $bootstrap->pdo();
$storageManager = $bootstrap->storageManager();
$imageService = $bootstrap->imageService();
$verificationService = $bootstrap->verificationService();
$oauthService = new OAuthProviderService($pdo);

$route = $_GET['route'] ?? 'home';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$user = $auth->user();

function redirectRoute(string $route, array $query = []): void
{
    $params = array_merge(['route' => $route], $query);
    $target = '/index.php' . ($params ? ('?' . http_build_query($params)) : '');
    header('Location: ' . $target);
    exit;
}

function render(string $template, array $params = []): void
{
    global $config, $user, $flash;
    extract($params, EXTR_SKIP);
    ob_start();
    include __DIR__ . '/templates/' . $template . '.php';
    $content = ob_get_clean();
    include __DIR__ . '/templates/layout.php';
}

function flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function decodeJsonOrRedirect(string $raw, string $errorMessage, string $redirectRoute = 'admin'): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        flash($errorMessage . '：' . json_last_error_msg());
        redirectRoute($redirectRoute);
    }
    return $data;
}

function uploadErrorMessage(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '上传图片过大，请压缩后重试',
        UPLOAD_ERR_PARTIAL => '上传未完成，请重新上传',
        UPLOAD_ERR_NO_FILE => '请先选择需要上传的图片',
        UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录缺失，请联系管理员',
        UPLOAD_ERR_CANT_WRITE => '服务器无法写入文件，请稍后再试',
        UPLOAD_ERR_EXTENSION => '服务器扩展阻止了上传，请联系管理员',
        default => '上传失败，请重试',
    };
}

switch ($route) {
    case 'home':
        $publicPuzzles = $imageService->listPuzzles();
        $storageList = $storageManager->all();
        render('home', compact('publicPuzzles', 'storageList', 'user'));
        break;
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            if ($auth->loginWithPassword($username, $password)) {
                flash('欢迎回来，' . $username, 'success');
                redirectRoute('dashboard');
            }

            flash('用户名或密码错误');
            redirectRoute('login');
        }
        $oauthConfig = $oauthService->all();
        render('login', compact('oauthConfig'));
        break;
    case 'oauth':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirectRoute('login');
        }
        $provider = trim($_POST['provider'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        if ($provider === '' || $subject === '' || $displayName === '') {
            flash('信息不完整');
            redirectRoute('login');
        }
        $auth->loginWithOAuth($provider, $subject, $displayName);
        flash('OAuth 登录成功', 'success');
        redirectRoute('dashboard');
    case 'logout':
        $auth->logout();
        flash('已安全退出');
        redirectRoute('home');
    case 'dashboard':
        if (!$user) {
            redirectRoute('login');
        }
        $myPuzzles = $imageService->listPuzzles((int)$user['id']);
        render('dashboard', compact('myPuzzles'));
        break;
    case 'create':
        if (!$user) {
            redirectRoute('login');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $colsInput = $_POST['cols'] ?? null;
                $rowsInput = $_POST['rows'] ?? null;
                $cols = filter_var($colsInput, FILTER_VALIDATE_INT) !== false ? (int)$colsInput : null;
                $rows = filter_var($rowsInput, FILTER_VALIDATE_INT) !== false ? (int)$rowsInput : null;
                if ($cols !== null) {
                    $cols = max(2, min(12, $cols));
                }
                if ($rows !== null) {
                    $rows = max(2, min(12, $rows));
                }
                $visibility = in_array($_POST['visibility'] ?? 'public', ['public', 'login'], true) ? $_POST['visibility'] : 'public';
                $storageId = (int)($_POST['storage_id'] ?? 0);
                if ($storageId <= 0) {
                    $storageId = (int)$storageManager->defaultStorage()['id'];
                }
                if (!isset($_FILES['image'])) {
                    throw new RuntimeException('请先选择需要上传的图片');
                }
                $error = (int)($_FILES['image']['error'] ?? UPLOAD_ERR_OK);
                if ($error !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(uploadErrorMessage($error));
                }
                $puzzle = $imageService->createFromUpload(
                    $_FILES['image'],
                    $storageId,
                    (int)$user['id'],
                    trim($_POST['title'] ?? '我的拼图'),
                    $visibility,
                    $cols,
                    $rows
                );
                flash('拼图生成成功！', 'success');
                redirectRoute('puzzle', ['slug' => $puzzle['slug']]);
            } catch (Throwable $e) {
                flash($e->getMessage());
                redirectRoute('home');
            }
        }
        redirectRoute('home');
    case 'puzzle':
        $slug = $_GET['slug'] ?? '';
        $puzzle = $imageService->getPuzzleBySlug($slug);
        if (!$puzzle) {
            http_response_code(404);
            echo '未找到拼图';
            exit;
        }
        if ($puzzle['visibility'] === 'login' && !$user) {
            flash('请先登录以查看此拼图');
            redirectRoute('login');
        }
        render('puzzle', compact('puzzle'));
        break;
    case 'share':
        $slug = $_GET['slug'] ?? '';
        $puzzle = $imageService->getPuzzleBySlug($slug);
        if (!$puzzle) {
            http_response_code(404);
            echo '未找到拼图';
            exit;
        }
        render('share', compact('puzzle'));
        break;
    case 'admin':
        $auth->requireAdmin();
        $storages = $storageManager->all();
        $oauthProviders = $oauthService->all();
        $verifications = $verificationService->overview();
        render('admin', compact('storages', 'oauthProviders', 'verifications'));
        break;
    case 'storage_save':
        $auth->requireAdmin();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? 'local');
        if ($name === '') {
            flash('图库名称不能为空');
            redirectRoute('admin');
        }
        if (!in_array($type, ['local', 'r2'], true)) {
            flash('不支持的图库类型');
            redirectRoute('admin');
        }
        $config = decodeJsonOrRedirect($_POST['config'] ?? '[]', '图库配置 JSON 无效');
        $storageManager->ensureStorageConfigDefaults($type, $config);
        $isDefault = isset($_POST['is_default']);
        $storageManager->save($name, $type, $config, $isDefault, $id);
        flash('图库配置已保存', 'success');
        redirectRoute('admin');
    case 'oauth_save':
        $auth->requireAdmin();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        if ($name === '' || $displayName === '') {
            flash('请填写完整的 OAuth 提供方信息');
            redirectRoute('admin');
        }
        $config = decodeJsonOrRedirect($_POST['config'] ?? '[]', 'OAuth 配置 JSON 无效');
        $oauthService->createOrUpdate($id, $name, $displayName, $config);
        flash('OAuth 配置已更新', 'success');
        redirectRoute('admin');
    case 'oauth_delete':
        $auth->requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        $oauthService->delete($id);
        flash('OAuth 配置已删除', 'success');
        redirectRoute('admin');
    case 'verification_token':
        if (!$user) {
            redirectRoute('login');
        }
        $token = $verificationService->getOrCreateToken((int)$user['id']);
        render('verification', compact('token'));
        break;
    case 'media':
        $storageId = (int)($_GET['storage'] ?? 0);
        $file = $_GET['file'] ?? '';
        if ($file === '' || str_contains($file, '..')) {
            http_response_code(404);
            exit('未找到资源');
        }
        $storage = $storageId > 0 ? $storageManager->get($storageId) : $storageManager->defaultStorage();
        if (!$storage || $storage['type'] !== 'local') {
            http_response_code(404);
            exit('未找到资源');
        }
        $config = json_decode((string)$storage['config_json'], true) ?? [];
        $storageManager->ensureStorageConfigDefaults('local', $config);
        $path = rtrim($config['path'], '/') . '/' . basename($file);
        if (!is_file($path)) {
            http_response_code(404);
            exit('未找到资源');
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    case 'puzzle_data':
        $slug = $_GET['slug'] ?? '';
        $puzzle = $imageService->getPuzzleBySlug($slug);
        if (!$puzzle) {
            http_response_code(404);
            echo json_encode(['error' => '未找到拼图']);
            exit;
        }
        if ($puzzle['visibility'] === 'login' && !$user) {
            http_response_code(403);
            echo json_encode(['error' => '需要登录后访问']);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode([
            'title' => $puzzle['title'],
            'created_at' => $puzzle['created_at'],
            'image' => $puzzle['public_url'],
            'grid' => [
                'cols' => (int)$puzzle['grid_cols'],
                'rows' => (int)$puzzle['grid_rows'],
            ],
            'visibility' => $puzzle['visibility'],
            'slug' => $puzzle['slug'],
        ]);
        exit;
    case 'verification_callback':
        $input = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }
        $token = $input['token'] ?? ($_GET['token'] ?? '');
        $successValue = $input['success'] ?? ($_GET['success'] ?? '0');
        $success = $successValue === '1' || $successValue === 1 || $successValue === true || $successValue === 'true';
        if ($token === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => '缺少 token 参数']);
            exit;
        }
        $recorded = $verificationService->recordVerification($token, $success);
        header('Content-Type: application/json');
        echo json_encode(['ok' => $recorded]);
        exit;
    default:
        http_response_code(404);
        echo '未找到页面';
}
