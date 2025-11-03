<?php
/** @var array $puzzle */
?>
<div class="card">
    <h2><?= htmlspecialchars($puzzle['title']) ?></h2>
    <p class="muted"><?= (int)$puzzle['grid_cols'] ?> × <?= (int)$puzzle['grid_rows'] ?> 拼图 · 来自 <a href="<?= htmlspecialchars($puzzle['public_url']) ?>" target="_blank" rel="noopener">图库原图</a></p>
    <div class="puzzle-wrapper">
        <canvas id="puzzle-board" aria-label="拼图画布"></canvas>
        <div id="puzzle-success" class="alert success" style="display:none;">🎉 恭喜，拼图完成！</div>
        <?php $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; ?>
        <div class="share-link">
            <span>分享链接：</span>
            <code style="background:rgba(31,42,68,0.08);padding:0.4rem 0.8rem;border-radius:8px;"><?= htmlspecialchars($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/index.php?route=puzzle&slug=' . $puzzle['slug']) ?></code>
            <a class="btn" href="/index.php?route=share&slug=<?= urlencode($puzzle['slug']) ?>">复制说明</a>
        </div>
    </div>
</div>

<script type="module">
import { initPuzzle } from '/assets/js/puzzle.js';
const canvas = document.getElementById('puzzle-board');
const successBanner = document.getElementById('puzzle-success');
initPuzzle(canvas, {
    imageUrl: <?= json_encode($puzzle['public_url']) ?>,
    cols: <?= (int)$puzzle['grid_cols'] ?>,
    rows: <?= (int)$puzzle['grid_rows'] ?>,
    snapDistance: 18,
    onComplete: () => {
        successBanner.style.display = 'block';
        successBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>
