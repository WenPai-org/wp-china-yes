状态：草案（M0）· 2026-09-03 · 依据 linuxjoy 定稿 §7

# 编写模块

## 模块合同

接口定义见 [`docs/4.0-rewrite-plan.md`](../4.0-rewrite-plan.md) §5.3：

```php
interface Module {
    public function id(): string;
    public function register(): void;
}

interface ConditionalModule extends Module {
    public function enabled(Config $config, Environment $environment): bool;
}
```

补充约定（与 §5.2 一致，实现待 M1）：

- 模块之间通过接口依赖，不直接 `new` 其他 Service，也不读全局 option
- 构造函数只收依赖，不注册 WordPress 钩子
- 可选模块抛 `Throwable` 由内核捕获，诊断页显示，不阻断其它模块

`Config` 在 4.0 实现里对应 `Config\Repository`（类型名以 schema 落地为准，调用方式按下节）。

## 一步步

### 1. 目录与命名

```text
src/<Group>/<Name>/<Name>Module.php
```

例：`src/Connectivity/PublicAssets/PublicAssetsModule.php`  
类：`WenPai\ChinaYes\Connectivity\PublicAssets\PublicAssetsModule`

### 2. `id()` 命名规则

点分小写，`<group>.<name>`：

- `connectivity.wordpress_org`
- `connectivity.public_assets`
- `privacy.data_residency`
- `services.apps`
- `admin.notice_control`

与配置键同一路径，便于 `Repository::get( $this->id() )`。

### 3. 声明依赖与运行场景

模块声明：

- 依赖的其它模块 id（内核按拓扑注册）
- 运行场景：`admin` / `frontend` / `rest` / `cli` / `cron`（按请求只注册需要的）

形状待 M1（方法名可微调）：

```php
public function dependencies(): array { return []; }
public function contexts(): array { return [ 'admin', 'frontend' ]; }
```

### 4. 在 `register()` 里挂钩

```php
public function register(): void {
    add_filter( 'style_loader_src', [ $this, 'rewrite' ], 999, 2 );
    add_filter( 'script_loader_src', [ $this, 'rewrite' ], 999, 2 );
}
```

### 5. 配置读取

```php
$mode = $this->config->get( 'connectivity.public_assets' );
```

不要 `get_option( 'wpcy_settings' )`。稳定 option 键名见定稿 §7.5a-C：`wpcy_settings` / `wpcy_network_settings` / `wpcy_site_overrides` / `wpcy_site_identity`（结构内 `schema_version`）。细则 `docs/specs/config-schema.md`。

### 6. 启用条件

`enabled()` = 配置 + 环境 + entitlement，三者都过才注册：

```php
public function enabled( Config $config, Environment $environment ): bool {
    if ( $config->get( 'connectivity.public_assets' ) === 'off' ) {
        return false;
    }
    if ( ! $environment->allowsUrlRewrite() ) {
        return false;
    }
    return true;
}
```

受限免费层（安装包镜像、adminCDN、字体、截图）配额用尽 → 降级回原始上游，**永不让站点坏**。不在模块里写死额度数字。

### 7. 失败隔离

`register()` 或钩子回调抛 `Throwable` → 内核捕获 → 诊断页记一条 → 其它模块继续。禁止 `catch (\Throwable $e) { error_log( $e ); /* 当没发生 */ }`。

## 完整示例：`Connectivity/PublicAssets`

白名单替换公共库 URL；节点故障保留原 URL（定稿 §7.3 首发必须）。

```php
<?php
/**
 * Whitelist rewrite of public asset URLs; keep origin URL on node failure.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\PublicAssets;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

final class PublicAssetsModule implements ConditionalModule {
    public function __construct(
        private Repository $config,
        private AssetMap $map,
        private NodeHealth $health,
    ) {}

    public function id(): string {
        return 'connectivity.public_assets';
    }

    public function enabled( Config $config, Environment $environment ): bool {
        return $config->get( 'connectivity.public_assets' ) !== 'off'
            && $environment->allowsUrlRewrite();
    }

    public function register(): void {
        add_filter( 'style_loader_src', [ $this, 'rewrite' ], 999, 1 );
        add_filter( 'script_loader_src', [ $this, 'rewrite' ], 999, 1 );
    }

    public function rewrite( string $src ): string {
        $mapped = $this->map->replaceIfWhitelisted( $src );
        if ( $mapped === $src ) {
            return $src;
        }
        if ( ! $this->health->isReachable( $mapped ) ) {
            return $src; // 节点故障：保留原 URL
        }
        return $mapped;
    }
}
```

### 单元测试（`tests/Unit/Connectivity/PublicAssetsModuleTest.php`，待 M1 PHPUnit）

```php
public function test_keeps_origin_when_node_unhealthy(): void {
    $module = new PublicAssetsModule( $config, $map, $healthDown );
    $this->assertSame(
        'https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js',
        $module->rewrite( 'https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js' )
    );
}

public function test_rewrites_whitelisted_when_node_healthy(): void {
    $module = new PublicAssetsModule( $config, $map, $healthUp );
    $this->assertStringContainsString(
        'admincdn.',
        $module->rewrite( 'https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js' )
    );
}
```

白名单外的 URL 原样返回。具体镜像主机以 `docs/specs/*` 与 entitlement 为准，示例里的主机名不要当合同抄进生产。

### wp-env 断言（`tests/Integration`，待 M1）

```bash
npx wp-env run tests-cli wp eval '
  $src = apply_filters( "script_loader_src", "https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js", "jquery" );
  if ( $src === "" ) { throw new Exception( "rewrite dropped URL" ); }
'
```

节点被标故障时，断言 `$src` 仍是原始 jsDelivr URL。

## 与服务端交互的模块

适用于 `Telemetry` / `Privacy/DataResidency` / `Services/SiteBinding` / `Services/Entitlements` / `Services/Apps`：

| 要求 | 规则 |
|------|------|
| 超时 | `wp_remote_*` 超时 ≤ 10s（见 security.md） |
| 重试 | 有上限；幂等请求才重试 |
| 缓存 | 健康/权益用 transient，不写进设置 option |
| 降级 | 超时或 5xx → 用上次缓存或回原始上游；免费内核必须仍可用 |
| 幂等键 | 写操作带 idempotency key，避免重复绑定/重复 ingest |
| `request_id` | 生成并透传到服务端；日志可记（脱敏后） |

云桥入库接口未就位前，`DataResidency` **不改 URL**（只记录 B 档元数据）。A 档改写门禁见定稿 §7.1a。

## 模块清单与归属

摘自定稿 §7.3（产品范围以定稿为准；本表只方便对照 id）：

| 类别 | 模块 | 建议 id |
|------|------|---------|
| 首发必须 | `Connectivity/WordPressOrg` | `connectivity.wordpress_org` |
| 首发必须 | `Connectivity/PublicAssets` | `connectivity.public_assets` |
| 首发必须 | `Connectivity/Avatar` | `connectivity.avatar` |
| 首发必须 | `Diagnostics` | `diagnostics` |
| 首发必须 | `Telemetry`（2.1 全集，常开，界面不露出） | `telemetry` |
| 首发必须 | `Privacy/DataResidency` | `privacy.data_residency` |
| 首发必须 | `Services/SiteBinding` | `services.site_binding` |
| 首发必须 | `Services/Entitlements` | `services.entitlements` |
| 首发必须 | `Services/Apps`（小工具容器，§7.5a-A） | `services.apps` |
| 首发必须 | `Migration` | `migration` |
| 首发必须 | Multisite 网络策略 / `Core/Scope` | `core.scope` |
| 首发必须 | WP-CLI | `cli` |
| 首发可选 | `Admin/NoticeControl` | `admin.notice_control` |
| 首发可选 | `Integrations/Windfonts` | `integrations.windfonts` |
| 首发 | `Admin/Announcements`（固定源，§7.5a-B） | `admin.announcements` |
| 4.x 后置 | 托管 CDN、白标/代理商 | 不要在 4.0 首发领走 |

后台是整站 React 应用 + 恢复页（定稿 §7.5a-D），不是第四个“业务模块”，实现落在 `src/Admin/`。

## 反例：伪功能

3.x 里存在「只有开关和外链、没有行为」的条目（设置页有、Service 没有）。4.0 **不允许**：

- 后台出现开关，但 `register()` 不挂钩
- 只有 `https://wpcy.com/go/…` 按钮、没有对应模块或 entitlement 状态
- 用「隐藏 section」代替删除（违反 ADR-001）
- 把已删功能（飞行模式、评论、维护模式等，定稿 §7.1-8）用别的名字加回来

没有行为的能力：从注册、schema、迁移、宣传里删掉，不要留空壳。
