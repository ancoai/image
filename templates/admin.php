<?php
/** @var array $storages */
/** @var array $oauthProviders */
/** @var array $verifications */
?>
<div class="card">
    <h2>图库配置</h2>
    <p>支持本地与 R2（S3 协议）图库，默认使用本地存储。只有管理员可以修改。</p>
    <table class="table">
        <thead>
            <tr>
                <th>名称</th>
                <th>类型</th>
                <th>默认</th>
                <th>配置 (JSON)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($storages as $storage): ?>
            <tr>
                <td><?= htmlspecialchars($storage['name']) ?></td>
                <td><span class="badge"><?= htmlspecialchars($storage['type']) ?></span></td>
                <td><?= $storage['is_default'] ? '是' : '否' ?></td>
                <td><small style="font-family:monospace;white-space:pre-wrap;"><?= htmlspecialchars($storage['config_json']) ?></small></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h3>新增/编辑</h3>
    <form method="post" action="/index.php?route=storage_save">
        <input type="hidden" name="id" value="">
        <label>名称</label>
        <input type="text" name="name" placeholder="例如：本地图库" required>
        <label>类型</label>
        <select name="type">
            <option value="local">local</option>
            <option value="r2">r2</option>
        </select>
        <label>配置 JSON</label>
        <textarea name="config" rows="4" placeholder='{ "path": "/workspace/image/storage/local", "public_url": "/storage/local" }'></textarea>
        <label><input type="checkbox" name="is_default" value="1"> 设为默认图库</label>
        <button type="submit">保存图库</button>
    </form>
</div>

<div class="card">
    <h2>OAuth / OIDC 提供方</h2>
    <p>配置后普通用户即可通过对应提供方登录。</p>
    <?php if (empty($oauthProviders)): ?>
        <p class="muted">尚未配置第三方登录。</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>标识</th><th>名称</th><th>配置</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($oauthProviders as $provider): ?>
                    <tr>
                        <td><?= htmlspecialchars($provider['name']) ?></td>
                        <td><?= htmlspecialchars($provider['display_name']) ?></td>
                        <td><small style="font-family:monospace;white-space:pre-wrap;"><?= htmlspecialchars($provider['config_json']) ?></small></td>
                        <td><a href="/index.php?route=oauth_delete&id=<?= (int)$provider['id'] ?>" onclick="return confirm('确定删除?');">删除</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <h3>新增提供方</h3>
    <form method="post" action="/index.php?route=oauth_save">
        <input type="hidden" name="id" value="">
        <label>标识 (英文)</label>
        <input type="text" name="name" placeholder="例如: wechat" required>
        <label>显示名称</label>
        <input type="text" name="display_name" placeholder="例如: 微信登录" required>
        <label>配置 JSON</label>
        <textarea name="config" rows="4" placeholder='{ "client_id": "...", "client_secret": "...", "authorize_url": "...", "token_url": "..." }'></textarea>
        <button type="submit">保存提供方</button>
    </form>
</div>

<div class="card">
    <h2>验证接口概览</h2>
    <p>每个注册用户都可以生成独立的 HTML 验证片段，并通过 <code>/index.php?route=verification_callback</code> 接口上报人机验证结果。</p>
    <?php if (empty($verifications)): ?>
        <p class="muted">暂无验证记录。</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>用户</th><th>Token</th><th>HTML 片段</th><th>成功次数</th><th>最近验证</th></tr></thead>
            <tbody>
                <?php foreach ($verifications as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['username']) ?></td>
                        <td><code><?= htmlspecialchars($item['token']) ?></code></td>
                        <td><small style="font-family:monospace;white-space:pre-wrap;"><?= htmlspecialchars($item['html_snippet']) ?></small></td>
                        <td><?= (int)$item['successes'] ?> / <?= (int)$item['total_attempts'] ?></td>
                        <td><?= htmlspecialchars($item['last_verified_at'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
