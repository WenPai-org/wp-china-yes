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

## 计数与事件

连通性模块不要自己 `update_option( 'wpcy_stats' )` / `wpcy_events`。次数走 `do_action( 'wpcy_stats_increment', '<counter>', $n )`（`StatsModule` 转给 `Stats\Counters`，shutdown 一次写入）。事件走 `do_action( 'wpcy_events_record', '<type>', $vars )` 或直接 `Events::record()`；`title` / `detail` 只在 `Events` 按 rest-api §`/events` 模板生成。计数器名只允许 `/stats` 表里的 10 个；`outbound_blocked` 由 M-BLOCK-1 接 HttpBlock。恢复模式下 `increment` 不计，事件只记 `recovery_*`。

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

## 四个插槽（外部插件功能并入）

并入规范见 [ADR-005](../architecture/adr-005-feature-absorption.md)。模块只经下列四个插槽接入产品，不得直写别的模块。需要时再加：迁移映射（向 `Migration\Mappers` 注册旧 option 键 → 新键）、服务端规则（向签名 ruleset 增加键，不新增规则类型）。

### 1. 配置命名空间

键路径 `modules.<name>`，与 `Module::id()` 同一路径（点分小写）。读写只经 `Config\Repository`。字段进 [`docs/specs/config-schema.md`](../specs/config-schema.md)。连通性与并入的免费能力不加「配额」字段。

### 2. REST 子路径

挂在同一命名空间 `wpcy/v1` 下，路径 `wpcy/v1/<name>/*`，或挂在既有资源下的子路径（例：`/residency/protected`）。不另起 REST 命名空间。错误码前缀 `wpcy_`，形状见 [`docs/specs/rest-api.md`](../specs/rest-api.md)。

### 3. 设置界面注册

向「简单 / 高级」两档各注册若干行。由 M-UI 定义的注册接口消费；模块不自己 `add_menu_page`，不直写 `src/Admin/app/`。

接口名先定为（**待 M-UI 实现**）：

```php
SettingsRows::register( string $module, array $simple_rows, array $advanced_rows );
```

`$module` 为模块 id。`$simple_rows` / `$advanced_rows` 为行描述数组（键名由 M-UI 冻结；引擎任务只声明要注册哪些行，不实现本接口）。空数组表示该档无行。

### 4. 诊断注册

向诊断页注册一张只读卡或一段记录。不建独立请求日志表，不记完整 URL，不记来源回溯。数据由模块提供只读结构，UI 进设计门禁。

## 场景与作用域声明

场景与作用域是横切关注点（ADR-004 / ADR-005 B1-4）：模块声明每个开关在三种场景下的默认值与允许的作用域；**不在模块内自行判断「是不是国内站」**（不读 geo、不写 `if ( $profile === 'domestic' )` 一类分支来决定默认；运行时闸若规格写明——例如驻留 A 档跟 `profile`——由该规格指定的模块读取有效 `profile`，仍不是「判断是不是国内站」）。

PHP 数组形态（方法名可微调，键名写死）：

```php
public function profile_defaults(): array {
    return array(
        'modules.site_blocklist.enabled' => array(
            'domestic'    => true,
            'crossborder' => true,
            'mixed'       => true,
            'scopes'      => array( 'network' ),
        ),
    );
}
```

| 键 | 规则 |
|----|------|
| 外层键 | 配置路径，与 schema 一致 |
| `domestic` / `crossborder` / `mixed` | 该场景默认值；类型与 schema 该字段相同 |
| `scopes` | 允许的作用域。站点级开关用 `array( 'site' )`；网络级不可被站点覆盖用 `array( 'network' )`；可被覆盖用 `array( 'network', 'site' )`。不是连通性的 `admin` / `frontend` / `both`（那是 `Connectivity\Scope`） |

`Profile::apply_defaults()` 只写入默认矩阵里列出的键；模块声明的默认若要进矩阵，必须先改 [`config-schema.md`](../specs/config-schema.md) D2 并经决定，不得在模块里偷偷写 option。

## 删除路径

每个并入模块的任务书必须写如何整体移除，保证以后能再拆出去（ADR-005 B1-7）。清单至少含：

1. Schema / Defaults / 默认矩阵中的 `modules.<name>`（及子键）
2. `RestModule` 上该模块的路由
3. cron hook、transient、自定义 option、自定义表
4. 词表行、诊断注册、设置行注册
5. `Plugin::create()` 的模块注册
6. 测试与 `tests/fixtures/` 中仅服务该模块的样本

卸掉后其它模块不得再引用该 id。不允许用「隐藏 section」代替删除（ADR-001）。

## 并入示例：`Privacy/SiteBlocklist`

第一份按 ADR-005 并入的能力（决定 A 节；不是把 HTTP Block 源码搬进来）。

| 项 | 值 |
|----|----|
| 目录 | `src/Privacy/SiteBlocklist/SiteBlocklistModule.php` |
| `id()` | `privacy.site_blocklist` |
| 配置 | `modules.site_blocklist`：`enabled`（boolean，默认 `true`）、`hosts[]`（上限 20，每条 `{ host, match: exact\|suffix, note }`）。**网络级，站点不可覆盖**（不进 `wpcy_site_overrides`） |
| REST | `GET/PUT /wpcy/v1/site-blocklist`（权限 `manage_network_options`）；命中受保护主机 → 400 `wpcy_blocklist_protected_host`，message「文派服务不可拦截」 |
| 设置行 | 简单档：无。高级档：一行「本站拦截清单」（编辑态由 M-BLOCK-UI）。`SettingsRows::register( 'privacy.site_blocklist', array(), array( /* 本站拦截清单 */ ) )`，待 M-UI |
| 诊断 | 只读卡「出站请求三层」+「测一条地址」（数据由引擎提供；UI 进 M-BLOCK-UI） |
| 场景默认 | 三场景 `enabled=true`；`scopes = array( 'network' )`。不在模块内判断国内站 |
| 服务端规则 | L0 `protected_hosts`、噪声包 `noise_block` 加在现有驻留 ruleset 上，不新规则类型 |
| 迁移 | 无（原插件未发布） |
| 删除路径 | 见 [`M-BLOCK-1.md`](../dev-plan/tasks/M-BLOCK-1.md)「删除路径」：去掉 schema 键、三条 REST、`pre_http_request` 钩子、诊断数据源、模块注册；无 cron、无自定义表 |

运行时：`pre_http_request` 顺序保证 L0 → L1 → L2。L2 只能 block，不能改道、不能放行 L0/L1。保存时拒绝保护主机；运行时对已存数据再过滤一层（静默忽略）。

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
| 首发（并入） | `Privacy/SiteBlocklist`（HTTP Block 能力重写，ADR-005） | `privacy.site_blocklist` |
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
- 把已删功能（飞行模式、评论、维护模式等，定稿 §7.1-8）用别的名字加回来。L2 本站拦截清单是已拍板的极窄例外（只拦、20 条、不能改道），不是飞行模式复活；不得借此口子加回正则/通配/全局屏蔽

没有行为的能力：从注册、schema、迁移、宣传里删掉，不要留空壳。

## 供应商层：用 `ProviderService`，不要直接读 `wpcy_secure_*`

供应商是独立于文派绑定的第二层账户（`docs/specs/providers.md`）。业务代码只经 `WenPai\ChinaYes\Providers\ProviderService` 连接、断开、测试与拉已购产品。

- 密钥在 `wpcy_secure_provider_{id}_license_key`（sodium secretbox，purpose `provider:{id}`）；instance 在 `wpcy_secure_provider_{id}_instance`。公开摘要在 `wpcy_providers`，不含密钥与完整邮箱。
- 不要 `get_option( 'wpcy_secure_provider_*' )`，不要把密钥写进 `wpcy_settings` / `wpcy_site_identity` / 网络 option。
- REST `/providers*` 由 `ProvidersController` 注册；响应不含密钥、完整邮箱、instance。
- 更新接通走 `UpdateBridge`（id `providers`），只对 `update_managed` 精确匹配项填 `pre_set_site_transient_update_plugins`。恢复模式下不挂钩。
