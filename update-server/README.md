# 短信转发助手 - PHP 版本管理器

一个简单但功能完整的 PHP 版本管理器，用于管理应用版本发布，用户可以通过 App 自动检测更新。

## ✨ 功能特性

- 📦 **版本上传** - 上传 APK 文件并发布新版本
- 📋 **版本列表** - 查看所有历史版本
- 🔗 **下载链接** - 自动生成 APK 下载链接
- 🔐 **密码保护** - 管理员密码保护上传和删除操作
- 📱 **App API** - 提供 API 供 App 检测更新
- 🎨 **美观界面** - 现代化 Web 管理界面
- 📊 **更新日志** - 支持版本更新日志

## 📁 文件结构

```
update-server/
├── .htaccess              # Apache 配置（保护敏感目录）
├── config.php             # 配置文件
├── api.php                # API 接口
├── index.html             # 管理后台界面
├── README.md              # 说明文档
├── apks/                  # APK 文件存储目录（自动创建）
└── data/                  # 版本数据存储目录（自动创建）
    └── versions.json      # 版本数据文件（自动创建）
```

## 🚀 快速开始

### 环境要求

- PHP 7.0 或更高版本
- Apache 或 Nginx 服务器
- 支持文件上传（file_uploads = On）

### 部署步骤

1. **上传文件**
   - 将整个 `update-server` 目录上传到你的 PHP 服务器

2. **修改配置**
   - 编辑 `config.php`，修改管理员密码
   ```php
   'admin_password' => '你的强密码',
   ```

3. **设置权限**
   - 确保 `apks/` 和 `data/` 目录可写（权限 755 或 777）
   ```bash
   chmod 755 apks/
   chmod 755 data/
   ```

4. **访问管理后台**
   - 打开浏览器访问：`https://your-domain.com/update-server/`
   - 开始上传版本！

## 🔌 API 接口

### 获取最新版本

```
GET /api.php?action=latest
```

**响应示例：**
```json
{
  "success": true,
  "data": {
    "versionName": "2.6.4",
    "changelog": "修复双卡 SIM 卡识别 bug",
    "apkFile": "sms-forwarder-v2.6.4.apk",
    "fileSize": 5242880,
    "releaseDate": "2026-04-05 10:30:00",
    "apkUrl": "https://your-domain.com/update-server/apks/sms-forwarder-v2.6.4.apk"
  }
}
```

### 获取所有版本列表

```
GET /api.php?action=list
```

### 上传新版本（需要密码）

```
POST /api.php?action=upload
Content-Type: multipart/form-data

参数：
- password: 管理员密码
- changelog: 更新日志（可选）
- apk: APK 文件（文件名格式：sms-forwarder-v2.6.4.apk）
```

### 删除版本（需要密码）

```
POST /api.php?action=delete
Content-Type: multipart/form-data

参数：
- password: 管理员密码
- versionName: 要删除的版本名称（如：2.6.4）
```

## 📱 App 集成

### 在 App 中检查更新

```kotlin
data class UpdateInfo(
    val versionName: String,
    val changelog: String,
    val apkUrl: String,
    val fileSize: Long,
    val releaseDate: String
)

suspend fun checkUpdate(): UpdateInfo? {
    val client = OkHttpClient()
    val request = Request.Builder()
        .url("https://your-domain.com/update-server/api.php?action=latest")
        .build()
    
    val response = client.newCall(request).execute()
    if (!response.isSuccessful) return null
    
    val json = response.body?.string() ?: return null
    val obj = JSONObject(json)
    
    if (!obj.getBoolean("success")) return null
    
    val data = obj.getJSONObject("data")
    return UpdateInfo(
        versionName = data.getString("versionName"),
        changelog = data.optString("changelog", ""),
        apkUrl = data.getString("apkUrl"),
        fileSize = data.getLong("fileSize"),
        releaseDate = data.getString("releaseDate")
    )
}
```

### 比较版本

```kotlin
fun hasUpdate(currentVersionName: String, latestVersionName: String): Boolean {
    return versionCompare(latestVersionName, currentVersionName) > 0
}
```

## 🔐 安全建议

1. **修改默认密码** - 立即修改 `config.php` 中的管理员密码
2. **使用 HTTPS** - 启用 HTTPS 保护数据传输
3. **限制访问** - 可以通过 IP 白名单限制管理后台访问
4. **定期备份** - 定期备份 `data/versions.json` 和 `apks/` 目录

## 🎨 自定义配置

### 修改 base_url

如果自动检测的 URL 不正确，可以在 `config.php` 中手动指定：

```php
'base_url' => 'https://your-domain.com/update-server',
```

### 修改上传限制

```php
'max_file_size' => 100 * 1024 * 1024, // 100MB
'allowed_types' => ['apk', 'apks'],
```

### 修改时区

```php
'timezone' => 'Asia/Shanghai',
```

## 📋 版本数据格式

`data/versions.json` 存储格式：

```json
[
  {
    "versionName": "2.6.4",
    "changelog": "修复双卡 SIM 卡识别 bug\n优化 subscriptionId 获取方式",
    "apkFile": "sms-forwarder-v2.6.4.apk",
    "fileSize": 5242880,
    "releaseDate": "2026-04-05 10:30:00"
  }
]
```

## ❓ 常见问题

### Q: 上传文件失败怎么办？
A: 检查以下几点：
1. `apks/` 目录权限是否正确
2. PHP 的 `upload_max_filesize` 和 `post_max_size` 是否足够大
3. `file_uploads` 是否开启

### Q: 文件名格式要求？
A: 请使用格式：`sms-forwarder-v2.6.4.apk`

### Q: 如何回退到旧版本？
A: 在管理后台删除新版本，旧版本会自动成为最新版本

### Q: 支持多个应用吗？
A: 当前版本只支持单个应用，可以通过部署多个实例来支持多个应用

## 📄 许可证

MIT License

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

---

**注意**：请妥善保管管理员密码，定期备份数据！
