<?php
/** @var array $puzzle */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$shareUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/index.php?route=puzzle&slug=' . $puzzle['slug'];
?>
<div class="card">
    <h2>分享「<?= htmlspecialchars($puzzle['title']) ?>」</h2>
    <p>将以下链接发送给朋友，邀请他们一起拼图。若设置为登录可见，好友需先登录。</p>
    <div class="share-link">
        <code style="background:rgba(31,42,68,0.08);padding:0.6rem 1rem;border-radius:12px;flex:1;">
            <?= htmlspecialchars($shareUrl) ?>
        </code>
        <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($shareUrl) ?>').then(()=>alert('已复制到剪贴板'))">复制</button>
    </div>
    <p class="muted" style="margin-top:1rem;">你也可以直接在社交媒体分享该链接。</p>
</div>
