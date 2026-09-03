# wp-china-yes v3.9.2 发版记录

## 背景

wptea.com（weixiaoduo-prod）WP 7.0 后台页面出现 `jQuery is not defined`，日志指向 `Service/Performance.php` 里过早反注册 `jquery-migrate`。

## 变更内容

### Service/Performance.php — 保留 jquery-migrate
- 删除前台 `init` 阶段对 `jquery-migrate` 的反注册
- 避免 WordPress 7.0+ 的 `jquery` 依赖链断裂

### 测试
- 新增 `tests/test-performance-jquery-migrate.php`
- 既有 `tests/test-settings-corruption-guard.php` 继续通过
