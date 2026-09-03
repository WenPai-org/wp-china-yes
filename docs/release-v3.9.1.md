# wp-china-yes v3.9.1 发版记录

## 背景

wptea.com（weixiaoduo-prod）WP 6.9.4 → 7.0 升级后，wp-china-yes 导致后台 jQuery 加载失败。DevOps 排查确认根因为 options 序列化数据损坏，清空重建 CDN 设置后临时修复。本次发版从代码层面增加防护。

## 变更内容

### helpers.php — get_settings() 入口防护
- `get_option('wp_china_yes')` 返回非数组值时（序列化损坏），直接置为 `[]`
- 阻止损坏数据进入 `wp_parse_args` 的 query-string 解析路径

### Service/Acceleration.php — 消除 (array) 强转
- 13 处 `in_array($val, (array) $settings[...])` 改为 `is_array()` + 直接传数组
- 涉及方法: has_admin_acceleration, has_frontend_acceleration, has_special_features, prepare_admin_replacements, prepare_frontend_replacements, prepare_public_library_replacements, prepare_dev_library_replacements, prepare_special_replacements, init_version_control, version_filter

### wp-china-yes.php — 版本号
- Version: 3.9.0 → 3.9.1
- CHINA_YES_VERSION: 3.9.0 → 3.9.1

### CHANGELOG.md — 新增条目
- v3.9.1 修复记录

## 测试

- 纯 PHP 测试: `tests/test-settings-corruption-guard.php`，19/19 通过
- 覆盖: 正常数组 / false / 损坏字符串 / 空字符串 / 数字 / null / stdClass 共 7 种输入
- PHP 语法检查: helpers.php + Acceleration.php 均通过

---

## 发版架构

```
GitHub (源码)          api.wenpai.org (更新分发)         生产站点 (weixiaoduo-prod 等)
    |                        |                               |
    | push + release         |                               |
    |------> v3.9.1 tag      |                               |
    |                        |                               |
    | 同学提供 ZIP           |                               |
    |------> 上传到 dl1      |                               |
    |                        |                               |
    |                        | 改 VersionCheck.php           |
    |                        | version + download_url        |
    |                        |------> PUC 轮询到更新          |
    |                        |                        <------|
```

## 更新分发机制

- 插件使用 Plugin Update Checker (PUC) v5，轮询 `https://api.wenpai.net/china-yes/version-check`
- api.wenpai.net DNS CNAME 到 api.wenpai.org（Cloudflare CDN）
- api.wenpai.org = wenpai.org 多站点网络 blog_id=10，路径 `/www/wwwroot/wenpai-org/`
- version-check 路由注册在 `plat-api` 插件: `wp-content/plugins/plat-api/API/ChinaYes/VersionCheck.php`
- **版本信息硬编码**在 `plugin_info` 数组中，非数据库驱动

## 涉及服务器

| 服务器 | IP | 角色 | SSH |
|--------|-----|------|-----|
| wenpai-org | 47.243.29.80 | wenpai.org 多站点 + api.wenpai.org 更新分发 | `ssh root@47.243.29.80` |
| weixiaoduo-prod | 47.120.1.115 | 8 个站点装了 wp-china-yes，含 wptea.com | `ssh weixiaoduo-prod` |
| feicode-prod | 45.117.8.70 | GitHub 镜像 + updates.wenpai.net（本插件不涉及） | `ssh feicode-prod` |

## weixiaoduo-prod 上的 wp-china-yes 站点

| 站点 | 路径 | 状态 |
|------|------|------|
| wptea.com | /www/wwwroot/wptea.com/wp-content/plugins/wp-china-yes/ | 已部署 v3.9.1 |
| www.weixiaoduo.com | /www/wwwroot/www.weixiaoduo.com/wp-content/plugins/wp-china-yes/ | 待部署 |
| www.weixiaoduo.cn | /www/wwwroot/www.weixiaoduo.cn/wp-content/plugins/wp-china-yes/ | 待部署 |
| www.weixiaoduo.net | /www/wwwroot/www.weixiaoduo.net/wp-content/plugins/wp-china-yes/ | 待部署 |
| www.modiqi.com | /www/wwwroot/www.modiqi.com/wp-content/plugins/wp-china-yes/ | 待部署 |
| www.feibisi.com | /www/wwwroot/www.feibisi.com/wp-content/plugins/wp-china-yes/ | 待部署 |
| updates.weixiaoduo.com | /www/wwwroot/updates.weixiaoduo.com/wp-content/plugins/wp-china-yes/ | 待部署 |
| files.weixiaoduo.com | /www/wwwroot/files.weixiaoduo.com/wp-content/plugins/wp-china-yes/ | 待部署 |

---

## 发版步骤

### Phase 1: GitHub 提交与发布

```bash
cd ~/Projects/wp-china-yes/

# 1.1 确认改动
git diff --stat
# helpers.php +7
# Service/Acceleration.php +27/-17
# wp-china-yes.php +2/-2 (版本号)
# CHANGELOG.md +8

# 1.2 提交
git add helpers.php Service/Acceleration.php wp-china-yes.php CHANGELOG.md
git commit -m "fix: add is_array guard to prevent corrupted options from breaking adminCDN replacements

Corrupted wp_china_yes options (e.g. truncated serialized data) could cause
wp_parse_args to parse as query-string, turning array fields into strings.
This led to malformed CDN replacement rules that broke jQuery loading in admin.

- get_settings(): reject non-array get_option results
- Acceleration: replace all (array) casts with is_array() guards (13 places)
- Bump version to 3.9.1"

# 1.3 推送到 GitHub
git push origin master

# 1.4 创建 tag + release
git tag v3.9.1
git push origin v3.9.1

# 1.5 在 GitHub Web 创建 Release（或 gh 命令）
# https://github.com/WenPai-org/wp-china-yes/releases/new?tag=v3.9.1
```

### Phase 2: 打包 ZIP（同学提供）

同学将 v3.9.1 代码打包为 ZIP，上传到下载服务器，提供 URL。
预期格式: `https://dl1.weixiaoduo.com/YYYY/MM/wp-china-yes.3.9.1.zip`

### Phase 3: 更新 version-check API

文件: wenpai-org (47.243.29.80)
`/www/wwwroot/wenpai-org/wp-content/plugins/plat-api/API/ChinaYes/VersionCheck.php`

修改 `$plugin_info` 数组中的三行:

```php
// 改前
"version"         => "3.8",
"download_url"    => "https://dl1.weixiaoduo.com/2025/01/wp-china-yes.3.8.zip",
"last_updated"    => "2025-01-01 22:00:00",

// 改后
"version"         => "3.9.1",
"download_url"    => "https://dl1.weixiaoduo.com/YYYY/MM/wp-china-yes.3.9.1.zip",  // 同学提供
"last_updated"    => "2026-05-21 HH:MM:SS",  // 填实际发布时间
```

### Phase 4: 验证 API 更新

```bash
# 确认 API 返回新版本
curl -s "https://api.wenpai.net/china-yes/version-check" | python3 -m json.tool | grep -E "version|download_url"
```

预期输出:
```json
"version": "3.9.1",
"download_url": "https://dl1.weixiaoduo.com/YYYY/MM/wp-china-yes.3.9.1.zip"
```

### Phase 5: 生产站点部署（逐个）

wptea.com 已完成。剩余 7 个站点逐个手动部署:

```bash
# 模板命令（替换 <site-dir>）
scp ~/Projects/wp-china-yes/helpers.php \
    weixiaoduo-prod:/www/wwwroot/<site-dir>/wp-content/plugins/wp-china-yes/helpers.php
scp ~/Projects/wp-china-yes/Service/Acceleration.php \
    weixiaoduo-prod:/www/wwwroot/<site-dir>/wp-content/plugins/wp-china-yes/Service/Acceleration.php
scp ~/Projects/wp-china-yes/wp-china-yes.php \
    weixiaoduo-prod:/www/wwwroot/<site-dir>/wp-content/plugins/wp-china-yes/wp-china-yes.php

# 部署后验证
ssh weixiaoduo-prod "grep 'CHINA_YES_VERSION' /www/wwwroot/<site-dir>/wp-content/plugins/wp-china-yes/wp-china-yes.php"
```

部署顺序建议（按重要性）:
1. ~~wptea.com~~ (已完成)
2. www.weixiaoduo.com
3. www.feibisi.com
4. www.modiqi.com
5. www.weixiaoduo.cn
6. www.weixiaoduo.net
7. updates.weixiaoduo.com
8. files.weixiaoduo.com

每个站点部署后验证:
- [ ] 后台正常加载，无 jQuery 报错
- [ ] wp-china-yes 设置页可访问
- [ ] 插件版本显示 3.9.1

### Phase 6: 归档

```bash
# 写入集群记忆
cluster-recall add --type lesson --title "wp-china-yes options 序列化损坏防护" \
  --content "WP 升级过程中 wp_china_yes options 序列化可能损坏，导致 adminCDN 替换规则异常。v3.9.1 增加两道防护: get_settings() 拒绝非数组输入 + Acceleration 全部用 is_array() 守卫替代 (array) 强转" \
  --source $(hostname -s) --date 2026-05-21 --tags "wp-china-yes,bugfix,serialization"
```
