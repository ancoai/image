<?php
/** @var array $myPuzzles */
?>
<div class="card">
    <h2>我的拼图</h2>
    <p>管理你创建的拼图，分享给朋友或再次挑战。</p>
    <a class="btn" href="/index.php?route=create">创建新拼图</a>
</div>

<div class="card">
    <?php if (empty($myPuzzles)): ?>
        <p class="muted">你还没有创建任何拼图。</p>
    <?php else: ?>
        <div class="gallery">
            <?php foreach ($myPuzzles as $puzzle): ?>
                <div class="gallery-item">
                    <img src="<?= htmlspecialchars($puzzle['public_url']) ?>" alt="<?= htmlspecialchars($puzzle['title']) ?>">
                    <div class="meta">
                        <h3 style="margin:0 0 0.5rem;"><?= htmlspecialchars($puzzle['title']) ?></h3>
                        <p class="muted" style="margin:0.5rem 0;">可见性：<?= $puzzle['visibility'] === 'public' ? '公开' : '登录可见' ?></p>
                        <p style="margin:0;">
                            <a href="/index.php?route=puzzle&slug=<?= urlencode($puzzle['slug']) ?>">开始拼图</a> ·
                            <a href="/index.php?route=share&slug=<?= urlencode($puzzle['slug']) ?>">复制链接</a>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
