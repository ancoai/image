<?php
/** @var array|null $user */
/** @var array $publicPuzzles */
/** @var array $storageList */
?>
<div class="card" style="background:linear-gradient(135deg, rgba(91,127,255,0.18), rgba(255,255,255,0.95));">
    <h1 style="margin-top:0;">创造属于你的拼图挑战</h1>
    <p>上传任意图片，我们会自动生成可在 PC 与移动端顺畅玩的拼图。拼合完成后将收到来自系统的祝贺，还可一键分享链接给好友。</p>
    <?php if ($user): ?>
        <a class="btn" href="/index.php?route=create">立即创建拼图</a>
    <?php else: ?>
        <p>登录后即可创建专属拼图。非管理员请使用 OAuth/OIDC 登录入口。</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>最新公开拼图</h2>
    <?php if (empty($publicPuzzles)): ?>
        <p class="muted">目前还没有公开拼图，快来创建第一份吧！</p>
    <?php else: ?>
        <div class="gallery">
            <?php foreach ($publicPuzzles as $puzzle): ?>
                <div class="gallery-item">
                    <img src="<?= htmlspecialchars($puzzle['public_url']) ?>" alt="<?= htmlspecialchars($puzzle['title']) ?>">
                    <div class="meta">
                        <h3 style="margin:0 0 0.5rem;"><?= htmlspecialchars($puzzle['title']) ?></h3>
                        <p style="margin:0;" class="muted"><?= (int)$puzzle['grid_cols'] ?> × <?= (int)$puzzle['grid_rows'] ?> · <a href="/index.php?route=puzzle&slug=<?= urlencode($puzzle['slug']) ?>">开始挑战</a></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($user): ?>
<div class="card">
    <h2>快速创建</h2>
    <form method="post" action="/index.php?route=create" enctype="multipart/form-data">
        <label>选择图片</label>
        <input type="file" name="image" accept="image/*" required>
        <label>拼图标题</label>
        <input type="text" name="title" value="我的拼图" required>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
            <div>
                <label>列数</label>
                <input type="number" name="cols" min="2" max="12" value="4">
            </div>
            <div>
                <label>行数</label>
                <input type="number" name="rows" min="2" max="12" value="3">
            </div>
            <div>
                <label>可见性</label>
                <select name="visibility">
                    <option value="public">公开</option>
                    <option value="login">登录可见</option>
                </select>
            </div>
            <div>
                <label>图库</label>
                <select name="storage_id">
                    <?php foreach ($storageList as $storage): ?>
                        <option value="<?= (int)$storage['id'] ?>"><?= htmlspecialchars($storage['name']) ?> (<?= htmlspecialchars($storage['type']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit">生成拼图</button>
    </form>
</div>
<?php endif; ?>
