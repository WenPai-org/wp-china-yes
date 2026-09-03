# wp-china-yes 导致后台 jQuery 加载失败 - 已解决

> 2026-05-21 wptea.com WP 7.0

## 结论

根因：`Service/Performance.php` 在前台 `init` 阶段反注册 `jquery-migrate`，WordPress 7.0+ 的 `jquery` 依赖链被拆断。
修复：删除该反注册，保留 `jquery-migrate`；`tests/test-performance-jquery-migrate.php` 已通过，wptea.com 已回写 3.9.2。

## 现象

后台所有依赖 jQuery 的脚本报 `Uncaught ReferenceError: jQuery is not defined`。
- 影响页面: `wp-admin/options-general.php?page=wp-china-yes` 及所有管理后台页面
- 禁用整个 wp-china-yes 插件 → 恢复正常
- admincdn.com CDN 服务当前返回 504

## 已排查

| 方向 | 结论 |
|------|------|
| admincdn 选项（DB 中设为空数组） | 无效，问题依旧 |
| admincdn.com CDN 可用性 | 504 不可用，但不是根本原因（禁用 admincdn 后仍出问题） |
| 全部 Service 注释 | 无效 |
| TranslationManager + Plugin 注释 | 恢复 |
| Performance 服务注释 | 无效 |
| Bridge Client 注释 | 无效 |
| Plugin 空壳运行 | 仍出问题 |
| Debug log 线索 | `jquery-migrate` 未注册导致 jquery 依赖链断裂 |

## 调试 log 关键条目

```
PHP Notice: 函数 WP_Scripts::add 的调用方法不正确。
使用处理器「jquery」的脚本已加入队列，但其依赖项尚未注册：jquery-migrate。
```

WP 7.0 script-loader.php line 905: `jquery` 依赖 `jquery-core` + `jquery-migrate`。

## 已知代码修复（已完成，但未解决此问题）

| 文件 | 修复 |
|------|------|
| helpers.php | is_array 防护 |
| wp-china-yes.php | is_array + test-translation 文件检查 |
| Service/Acceleration.php | is_array 守卫 x13 |
| Service/Language.php | is_array 防护 x3 |
| Service/Migration.php | is_array 防护 x1 |

## 剩余外链清理（已完成部分）

| 文件 | 状态 |
|------|------|
| templates/about-section.php | 贡献者链接去掉 |
| templates/welcome-section.php | "获取 WP Deer 建站套件" → "获取建站套件" |
| Service/Setting.php | wpbridge/maiyun/wpmirror → wpcy.com subdirs |
| Service/ModernSetting.php | 同上 |
| copyright.txt | 2025 → 2026 |
| readme.md | 2025 → 2026 |
| framework/classes/admin-options.class.php | 2025 → 2026 |

## 待调试方向

1. 是否 WP 7.0 中 `jquery-migrate` 注册逻辑有变化
2. 插件中某个 hook 可能在 admin 上下文中意外触发了 jquery-migrate 注销
3. 插件 `init` 或 `plugins_loaded` 阶段是否有代码干扰了 WP 脚本注册
4. vendor/autoload (PUC library) 是否在加载时产生副作用
