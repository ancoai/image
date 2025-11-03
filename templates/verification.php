<?php
/** @var array $token */
?>
<div class="card">
    <h2>验证片段</h2>
    <p>复制以下 HTML 代码并嵌入到你的站点中，用于呈现人机验证组件。完成验证后，将 token 与结果 POST 到 <code>/index.php?route=verification_callback</code>。</p>
    <textarea rows="5" readonly onclick="this.select();" style="font-family:monospace;"><?= htmlspecialchars($token['html_snippet']) ?></textarea>
    <p>示例请求：</p>
    <pre style="background:rgba(31,42,68,0.08);padding:1rem;border-radius:12px;overflow:auto;">fetch('/index.php?route=verification_callback', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ token: '<?= htmlspecialchars($token['token']) ?>', success: true })
});</pre>
</div>
