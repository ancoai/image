# 智能拼图工坊

使用 PHP + SQLite 实现的在线拼图平台：

- 上传任意图片自动切分为拼图，支持 PC 与移动端拖拽，提供自动吸附。
- 拼图完成后自动展示祝贺提示。
- 生成可分享的链接，可设置公开或登录可见。
- 支持本地图库与 Cloudflare R2（S3 兼容），默认启用本地图库。
- 管理员（初始账号 `admin/123456`）可配置图库、OAuth/OIDC 提供方，并查看验证概览。
- 普通用户仅能通过配置好的 OAuth/OIDC 登录，系统提供模拟入口便于开发调试。
- 提供验证码集成接口，用户可生成独立 HTML 片段并通过回调上报验证结果。

## 开发与运行

```bash
php -S localhost:8000 -t public
```

首次访问会自动初始化 SQLite 数据库与默认管理员账号。本地上传的图片保存在 `storage/local`，并通过 `public/storage` 符号链接对外提供访问。

R2 上传使用 S3 API V4 签名，需在后台配置 `account_id`、`access_key`、`secret_key`、`bucket`、`endpoint` 等字段。

验证码回调接口：`POST /index.php?route=verification_callback`，支持 `application/json` 或标准表单提交格式。
