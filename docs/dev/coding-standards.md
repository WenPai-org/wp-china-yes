状态：草案（M0）· 2026-09-03 · 依据 linuxjoy 定稿 §7

# 编码规范

脚手架配置文件（`phpcs.xml.dist`、`phpstan.neon.dist`、ESLint 等）**只在本文给建议片段，不在本任务创建**。落地是 M1。

## PHP

### 版本

- 4.0 目标 **PHP 8.1**
- 下限待 3.9.3 兼容性报告发出后 30 天看版本盘再定；**底线 8.0** —— 待定（M0 / 等 P1 数据）
- WordPress 下限已定：**6.5**
- 每个 PHP 文件：`declare(strict_types=1);`
- 类型声明全覆盖（参数、返回、属性）；不用无类型的 public 字段

### 命名空间与文件

- 命名空间：`WenPai\ChinaYes\`
- PSR-4；一类一文件；文件名与类名一致（`PublicAssetsModule.php` 对应 `PublicAssetsModule`）
- **这是 4.0 `src/` 的约定。** 3.x 与 `client/` 旧文件（`class-*.php`、仓根 `Service/`）不追溯改名。
- Composer autoload：4.0 映射 `WenPai\ChinaYes\` → `src/`（现状 `composer.json` 仍映射 `./`，含 `files` 加载 `framework/`——M1 去掉）

### 代码风格

WPCS `WordPress-Extra` + `WordPress-Docs`，但排除与 PSR-4 冲突的文件名规则和 Yoda 条件。

建议 `phpcs.xml.dist` 片段（待 M1）：

```xml
<?xml version="1.0"?>
<ruleset name="WPCY 4.0">
    <description>WordPress Extra + Docs, PSR-4 filenames, no Yoda.</description>
    <file>src</file>
    <file>tests</file>
    <exclude-pattern>vendor/*</exclude-pattern>
    <exclude-pattern>framework/*</exclude-pattern>
    <exclude-pattern>client/*</exclude-pattern>
    <rule ref="WordPress-Extra"/>
    <rule ref="WordPress-Docs"/>
    <rule ref="WordPress.Files.FileName">
        <severity>0</severity>
    </rule>
    <rule ref="WordPress.PHP.YodaConditions">
        <severity>0</severity>
    </rule>
</ruleset>
```

### 静态分析

PHPStan level 6 + `szepeviktor/phpstan-wordpress`。

建议 `phpstan.neon.dist` 片段（待 M1）：

```neon
includes:
    - vendor/szepeviktor/phpstan-wordpress/extension.neon

parameters:
    level: 6
    paths:
        - src
    excludePaths:
        - framework
        - client
    bootstrapFiles:
        - tests/phpstan-bootstrap.php
```

### 禁区

- 不 `require` / Composer `files` 加载 `framework/`
- 业务模块不直接 `get_option` / `update_option`；经 `Config\Repository`
- 模块构造函数不注册钩子；在 `register()` 里挂
- 不 `error_log` 后假装成功；失败必须进入诊断可见状态
- 远程调用只返回结果对象或 `WP_Error`，不返回真假混合值
- 不写用户可见的「遥测」「匿名数据」文案（报告常开，界面不露出；定稿 §7.1-1）
- 商业链接一律 `https://wpcy.com/go/…`，禁止直写第三方域名
- 不在本地判断套餐；配额与降级由服务端 entitlement 决定

### 安全

所有输入 sanitize、所有输出 escape、所有写操作 capability + nonce。细则见 [security.md](security.md)。

### i18n

- text domain：`wp-china-yes`（与插件头一致）
- 用户可见字符串：`__()` / `esc_html__()` / `esc_attr__()` / `_n()` 等
- 翻译源在文派翻译平台；仓内只放 `languages/*.pot`，由 `wp i18n make-pot` 生成
- 不提交手工维护的 `.po/.mo` 作为 4.0 真源（3.x `framework/languages/` 不追溯）

### 错误与日志

统一 `Logger` 接口（级别 + 上下文数组）。生产默认 **warning 及以上**。日志脱敏见 [security.md](security.md)（URL 去查询串、不记邮箱/IP/凭据；`request_id` 可记）。

接口形状（待 M1 落地，名称可微调，语义不可改）：

```php
interface Logger {
    public function log(string $level, string $message, array $context = []): void;
}
```

## JavaScript / React

- 工具链全部来自 `@wordpress/scripts`：ESLint（`plugin:@wordpress/eslint-plugin/recommended`）、Prettier（`@wordpress/prettier-config`）、Babel、webpack。**不自配。**
- 组件：只用 `@wordpress/components`
- 状态：`@wordpress/data`
- 请求：`@wordpress/api-fetch`（走 WP REST + nonce）
- i18n：`@wordpress/i18n`

目录（后台应用，定稿 §7.5a-D）：

```text
src/Admin/app/
├── index.js
├── store/
├── pages/
├── components/
└── hooks/
```

禁区：

- 不引第三方 UI 库
- 不在前端判断套餐（只渲染服务端给的 entitlement 事实）
- 不直接 `fetch` / `XMLHttpRequest`

另保留服务端渲染的恢复页 `?page=wpcy-recovery`（无 JS 也能关 URL 改写与模块）。恢复页不走本应用。

## CSS

- 只用 CSS 变量
- 品牌 token 来自 `tokens.css`（由 linuxjoy `docs/plans/tokens/wpcy-brand-tokens.json` 生成——**生成物，禁止手改**）
- 只覆盖 `--wp-admin-theme-color*` 与自有 `--wpcy-*`
- 不写裸 hex；绿色不得同时当品牌色和成功态（成功态用 `status.success` 对应变量）
- 图标：Phosphor SVG，不引图标字体

## 通用

### 文件头

PHP：

```php
<?php
/**
 * Public assets URL rewrite module.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\PublicAssets;
```

JS：

```js
/**
 * Overview page.
 *
 * @package WenPai\ChinaYes
 */
```

### 依赖

- Composer：只有生产依赖进发布包（`composer install --no-dev`，见现有 `scripts/build-release.sh`）
- npm：**只** `devDependencies`（现状 `package.json` 已如此：仅 `@wordpress/env`）。运行时 JS 由 `@wordpress/scripts` 编进发布包，不把 `node_modules` 打进 ZIP

### 提交前本地检查（建议脚本名，待 M1 脚手架落地）

```bash
composer lint          # PHPCS
composer analyse       # PHPStan
npm run lint           # ESLint via @wordpress/scripts
npm run format:check   # Prettier
```

在这些脚本进 `composer.json` / `package.json` 之前，用 M1 文档里的等价命令；3.9.x 线继续 `bash tests/run-tests.sh`。
