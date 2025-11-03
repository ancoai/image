<?php
/** @var array $oauthConfig */
?>
<div class="card">
    <h2>管理员登录</h2>
    <form method="post" action="/index.php?route=login">
        <label>用户名</label>
        <input type="text" name="username" required>
        <label>密码</label>
        <input type="password" name="password" required>
        <button type="submit">登录</button>
    </form>
</div>

<div class="card">
    <h2>OAuth / OIDC 登录</h2>
    <?php if (empty($oauthConfig)): ?>
        <p class="muted">管理员尚未配置第三方登录。</p>
    <?php else: ?>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
            <?php foreach ($oauthConfig as $provider): ?>
                <form method="post" action="/index.php?route=oauth" class="card" style="box-shadow:none;padding:1rem;background:rgba(91,127,255,0.08);">
                    <input type="hidden" name="provider" value="<?= htmlspecialchars($provider['name']) ?>">
                    <p style="margin-top:0;font-weight:600;"><?= htmlspecialchars($provider['display_name']) ?></p>
                    <label>模拟授权用户 ID</label>
                    <input type="text" name="subject" placeholder="例如 openid-12345" required>
                    <label>显示昵称</label>
                    <input type="text" name="display_name" placeholder="用户昵称" required>
                    <button type="submit">模拟登录</button>
                    <p class="muted" style="font-size:0.85rem;">在实际部署中，请替换为真实的 OAuth/OIDC 流程回调。</p>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
