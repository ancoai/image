<?php
/** @var array $puzzle */
?>
<div class="card">
    <h2><?= htmlspecialchars($puzzle['title']) ?></h2>
    <p class="muted"><?= (int)$puzzle['grid_cols'] ?> × <?= (int)$puzzle['grid_rows'] ?> 拼图 · 来自 <a href="<?= htmlspecialchars($puzzle['public_url']) ?>" target="_blank" rel="noopener">图库原图</a></p>

    <div class="puzzle-stats">
        <div>完成度：<strong id="puzzle-progress">0%</strong></div>
        <div>用时：<strong id="puzzle-timer">00:00</strong> <span class="muted">最佳 <span id="puzzle-best">--</span></span></div>
    </div>

    <ul class="insight-list" style="margin-top:1.2rem;">
        <li><strong>智能容错</strong>自动根据图块大小调节吸附判定，允许轻微偏差并提醒剩余块数。</li>
        <li><strong>本地纪录</strong>每次完成后都会在本地保存你的最佳成绩，下次挑战即可对比进步。</li>
        <li><strong>安心保护</strong>控件支持一键恢复初始状态，方便教学或多人轮流体验。</li>
    </ul>

    <div class="puzzle-controls" style="margin-top:1.5rem;">
        <button type="button" id="shuffle-loose">重新打乱未归位</button>
        <button type="button" id="reset-puzzle">彻底重开</button>
        <button type="button" id="toggle-ghost" data-ghost="1">隐藏底图辅助</button>
        <button type="button" id="peek-original">2 秒预览原图</button>
        <label>吸附距离<input type="range" id="snap-range" min="8" max="160" value="18"><span id="snap-value">18</span>px</label>
        <label>底图透明度<input type="range" id="ghost-range" min="0" max="100" value="35"><span id="ghost-value">35%</span></label>
    </div>

    <div class="puzzle-wrapper">
        <canvas id="puzzle-board" aria-label="拼图画布"></canvas>
        <div id="puzzle-error" class="alert error" style="display:none;"></div>
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

    <div class="puzzle-experimental">
        <strong>概念功能实验室</strong>
        <p class="muted">这些富有想象力的能力仍在探索阶段，欢迎向管理员反馈你的灵感：</p>
        <ul>
            <li>全息投影拼图，支持在桌面上投射虚拟拼图块。</li>
            <li>语音助手随时回答“下一块在哪？”的贴心提示。</li>
            <li>脑机接口联动，凭借思考即可移动拼图，敬请期待。</li>
        </ul>
    </div>
</div>

<script type="module">
import { initPuzzle } from '/assets/js/puzzle.js';

const canvas = document.getElementById('puzzle-board');
const successBanner = document.getElementById('puzzle-success');
const errorBanner = document.getElementById('puzzle-error');
const progressEl = document.getElementById('puzzle-progress');
const timerEl = document.getElementById('puzzle-timer');
const bestEl = document.getElementById('puzzle-best');
const shuffleLooseBtn = document.getElementById('shuffle-loose');
const resetBtn = document.getElementById('reset-puzzle');
const toggleGhostBtn = document.getElementById('toggle-ghost');
const peekBtn = document.getElementById('peek-original');
const snapRange = document.getElementById('snap-range');
const ghostRange = document.getElementById('ghost-range');
const snapValue = document.getElementById('snap-value');
const ghostValue = document.getElementById('ghost-value');

const slug = <?= json_encode($puzzle['slug']) ?>;
const bestKey = `puzzle-best-${slug}`;
const storedBest = parseFloat(window.localStorage.getItem(bestKey) ?? '0');
let currentBest = storedBest > 0 ? storedBest : 0;
if (currentBest > 0) {
    bestEl.textContent = formatTime(currentBest);
}

let timerHandle = null;

function formatTime(seconds) {
    const totalSeconds = Math.max(0, Math.floor(seconds));
    const mins = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
    const secs = String(totalSeconds % 60).padStart(2, '0');
    return `${mins}:${secs}`;
}

function startTimer(board) {
    if (timerHandle !== null || board.completed) {
        return;
    }
    timerHandle = window.setInterval(() => {
        timerEl.textContent = formatTime(board.getElapsedSeconds());
    }, 250);
}

function stopTimer(board) {
    if (timerHandle !== null) {
        window.clearInterval(timerHandle);
        timerHandle = null;
    }
    timerEl.textContent = formatTime(board.getElapsedSeconds());
}

function updateProgressDisplay(placed, total) {
    const percent = total > 0 ? Math.round((placed / total) * 100) : 0;
    progressEl.textContent = `${percent}% (${placed}/${total})`;
}

const board = initPuzzle(canvas, {
    imageUrl: <?= json_encode($puzzle['public_url']) ?>,
    cols: <?= (int)$puzzle['grid_cols'] ?>,
    rows: <?= (int)$puzzle['grid_rows'] ?>,
    snapDistance: parseInt(snapRange.value, 10),
    onComplete: () => {
        successBanner.style.display = 'block';
        successBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
        stopTimer(board);
        const elapsed = board.getElapsedSeconds();
        if (!currentBest || elapsed < currentBest) {
            currentBest = elapsed;
            window.localStorage.setItem(bestKey, String(elapsed));
            bestEl.textContent = formatTime(elapsed);
        }
    },
    onError: (message) => {
        if (!errorBanner) {
            return;
        }
        errorBanner.textContent = message;
        errorBanner.style.display = 'block';
        successBanner.style.display = 'none';
    },
    onProgress: (placed, total) => {
        updateProgressDisplay(placed, total);
        if (placed === 0 && !board.startedAt) {
            if (timerHandle !== null) {
                stopTimer(board);
            }
            timerEl.textContent = '00:00';
            return;
        }
        if (!board.completed) {
            startTimer(board);
        }
    },
    onShuffle: () => {
        successBanner.style.display = 'none';
        errorBanner.style.display = 'none';
        if (timerHandle !== null) {
            stopTimer(board);
        }
        timerEl.textContent = '00:00';
    },
});

const initialProgress = board.progress();
updateProgressDisplay(initialProgress.placed, initialProgress.total);

shuffleLooseBtn.addEventListener('click', () => {
    board.shufflePieces(true);
});

resetBtn.addEventListener('click', () => {
    board.reset();
});

toggleGhostBtn.addEventListener('click', () => {
    const currentlyVisible = toggleGhostBtn.dataset.ghost !== '0';
    const opacity = board.setGhostVisible(!currentlyVisible);
    toggleGhostBtn.dataset.ghost = opacity > 0 ? '1' : '0';
    toggleGhostBtn.textContent = opacity > 0 ? '隐藏底图辅助' : '显示底图辅助';
    ghostRange.value = String(Math.round(opacity * 100));
    ghostValue.textContent = `${Math.round(opacity * 100)}%`;
});

peekBtn.addEventListener('click', () => {
    board.peekOriginal(2200);
});

snapRange.addEventListener('input', () => {
    const applied = Math.round(board.setSnapDistance(parseInt(snapRange.value, 10)));
    snapValue.textContent = applied;
    snapRange.value = String(applied);
});

ghostRange.addEventListener('input', () => {
    const opacity = Math.max(0, Math.min(100, parseInt(ghostRange.value, 10)));
    const normalized = board.setGhostOpacity(opacity / 100);
    ghostValue.textContent = `${Math.round(normalized * 100)}%`;
    toggleGhostBtn.dataset.ghost = normalized > 0 ? '1' : '0';
    toggleGhostBtn.textContent = normalized > 0 ? '隐藏底图辅助' : '显示底图辅助';
});

window.addEventListener('beforeunload', () => {
    board.destroy();
    if (timerHandle !== null) {
        window.clearInterval(timerHandle);
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
