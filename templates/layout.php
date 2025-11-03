<?php
/** @var array $config */
/** @var array|null $user */
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['app_name']) ?><?= isset($title) ? ' · ' . htmlspecialchars($title) : '' ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header>
    <div class="brand">🧩 <?= htmlspecialchars($config['app_name']) ?></div>
    <nav>
        <?php if ($user): ?>
            <span style="margin-right:1rem;">你好，<?= htmlspecialchars($user['username']) ?><?php if ($user['is_admin']): ?> <span class="badge">管理员</span><?php endif; ?></span>
            <a class="btn" href="/index.php?route=dashboard">我的拼图</a>
            <?php if ($user['is_admin']): ?>
                <a class="btn" style="margin-left:0.5rem;" href="/index.php?route=admin">后台</a>
            <?php endif; ?>
            <a class="btn" style="margin-left:0.5rem;" href="/index.php?route=logout">退出</a>
        <?php else: ?>
            <a class="btn" href="/index.php?route=login">登录</a>
        <?php endif; ?>
    </nav>
</header>
<main>
    <?php if (!empty($flash)): ?>
        <div class="alert<?= $flash['type'] === 'success' ? ' success' : '' ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>
    <?= $content ?? '' ?>
</main>
<script type="module">
import { initPuzzle } from '/assets/js/puzzle.js';
window.PuzzleApp = { initPuzzle };
</script>
</body>
</html>
