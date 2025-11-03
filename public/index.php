<?php

require __DIR__ . '/../index.php';
declare(strict_types=1);

use App\Bootstrap;
use App\OAuthProviderService;
use App\VerificationService;

require __DIR__ . '/../autoload.php';

$config = require __DIR__ . '/../config.php';
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

function render(string $template, array $params = []): void
{
    global $config, $user, $flash;
    extract($params, EXTR_SKIP);
    ob_start();
    include __DIR__ . '/../templates/' . $template . '.php';
    $content = ob_get_clean();
    include __DIR__ . '/../templates/layout.php';
}

function flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
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
                header('Location: /index.php?route=dashboard');
            } else {
                flash('用户名或密码错误');
                header('Location: /index.php?route=login');
            }
            exit;
        }
        $oauthConfig = $oauthService->all();
        render('login', compact('oauthConfig'));
        break;
    case 'oauth':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /index.php?route=login');
            exit;
        }
        $provider = trim($_POST['provider'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        if ($provider === '' || $subject === '' || $displayName === '') {
            flash('信息不完整');
            header('Location: /index.php?route=login');
            exit;
        }
        $auth->loginWithOAuth($provider, $subject, $displayName);
        flash('OAuth 登录成功', 'success');
        header('Location: /index.php?route=dashboard');
        exit;
    case 'logout':
        $auth->logout();
        flash('已安全退出');
        header('Location: /index.php');
        exit;
    case 'dashboard':
        if (!$user) {
            header('Location: /index.php?route=login');
            exit;
        }
        $myPuzzles = $imageService->listPuzzles((int)$user['id']);
        render('dashboard', compact('myPuzzles'));
        break;
    case 'create':
        if (!$user) {
            header('Location: /index.php?route=login');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $cols = max(2, min(12, (int)($_POST['cols'] ?? 4)));
                $rows = max(2, min(12, (int)($_POST['rows'] ?? 3)));
                $visibility = in_array($_POST['visibility'] ?? 'public', ['public', 'login'], true) ? $_POST['visibility'] : 'public';
                $storageId = (int)($_POST['storage_id'] ?? 0);
                if ($storageId <= 0) {
                    $storageId = (int)$storageManager->defaultStorage()['id'];
                }
                $puzzle = $imageService->createFromUpload($_FILES['image'], $storageId, (int)$user['id'], trim($_POST['title'] ?? '我的拼图'), $visibility, $cols, $rows);
                flash('拼图生成成功！', 'success');
                header('Location: /index.php?route=puzzle&slug=' . urlencode($puzzle['slug']));
            } catch (Throwable $e) {
                flash($e->getMessage());
                header('Location: /index.php?route=home');
            }
            exit;
        }
        header('Location: /index.php?route=home');
        exit;
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
            header('Location: /index.php?route=login');
            exit;
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
        $config = json_decode($_POST['config'] ?? '[]', true) ?? [];
        $isDefault = isset($_POST['is_default']);
        $storageManager->save($name, $type, $config, $isDefault, $id);
        flash('图库配置已保存', 'success');
        header('Location: /index.php?route=admin');
        exit;
    case 'oauth_save':
        $auth->requireAdmin();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $config = json_decode($_POST['config'] ?? '[]', true) ?? [];
        $oauthService->createOrUpdate($id, $name, $displayName, $config);
        flash('OAuth 配置已更新', 'success');
        header('Location: /index.php?route=admin');
        exit;
    case 'oauth_delete':
        $auth->requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        $oauthService->delete($id);
        flash('OAuth 配置已删除', 'success');
        header('Location: /index.php?route=admin');
        exit;
    case 'verification_token':
        if (!$user) {
            header('Location: /index.php?route=login');
            exit;
        }
        $token = $verificationService->getOrCreateToken((int)$user['id']);
        render('verification', compact('token'));
        break;
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
        $verificationService->recordVerification($token, $success);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    default:
        http_response_code(404);
        echo '未找到页面';
}
