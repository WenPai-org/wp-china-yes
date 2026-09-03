状态：草案（M0）· 2026-09-03 · 依据 linuxjoy 定稿 §7

# 安全

## 清单

每条：正例 / 反例。合入前对照。

### Capability

单站写操作：`manage_options`。多站点网络设置：`manage_network_options`。

```php
// 正例
if ( is_multisite() && is_network_admin() ) {
    if ( ! current_user_can( 'manage_network_options' ) ) {
        wp_die( esc_html__( 'Forbidden.', 'wp-china-yes' ), 403 );
    }
} elseif ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Forbidden.', 'wp-china-yes' ), 403 );
}

// 反例：只登录即可改连接
if ( is_user_logged_in() ) {
    update_option( 'wpcy_settings', $in );
}
```

REST 路由：`permission_callback` 里做同样检查，不要只靠「隐藏菜单」。

### Nonce

- 表单：`wp_nonce_field` + `check_admin_referer`
- REST：cookie 会话 + 头 `X-WP-Nonce`（`@wordpress/api-fetch` 默认带）；`wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' )`

```php
// 正例 · 表单
wp_nonce_field( 'wpcy_save_settings', 'wpcy_settings_nonce' );
if ( ! check_admin_referer( 'wpcy_save_settings', 'wpcy_settings_nonce' ) ) {
    wp_die( esc_html__( 'Invalid nonce.', 'wp-china-yes' ), 403 );
}

// 正例 · REST
register_rest_route( 'wpcy/v1', '/settings', [
    'methods'             => 'POST',
    'permission_callback' => static function ( WP_REST_Request $request ): bool {
        return current_user_can( 'manage_options' )
            && wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
    },
    'callback'            => [ $controller, 'update' ],
] );

// 反例：小工具 iframe 拿到 WP nonce
wp_localize_script( 'wpcy-app-bridge', 'wpcyApp', [ 'nonce' => wp_create_nonce( 'wp_rest' ) ] );
```

小工具拿不到 nonce（见下文容器）。

### Sanitize 输入（按类型）

```php
// 正例
$mode   = sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) );
$url    = esc_url_raw( wp_unslash( $_POST['entry_url'] ?? '' ) );
$flag   = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
$ids    = array_map( 'sanitize_key', (array) $request->get_param( 'permissions' ) );
$int    = absint( $request->get_param( 'schema_version' ) );

// 反例
$mode = $_POST['mode'];
$json = json_decode( file_get_contents( 'php://input' ), true ); // 未校验 schema
```

配置写入必须过 `Config\Schema` / Validator，不把原始 POST 整包 `update_option`。

### Escape 输出

```php
// 正例
echo esc_html( $module_id );
echo esc_attr( $option_value );
echo esc_url( $rewrite_src );
echo wp_kses_post( $announcement_html ); // 公告 HTML 白名单

// 反例
echo $module_id;
echo '<a href="' . $rewrite_src . '">';
echo $announcement_html;
```

JS 里用 `@wordpress/i18n` 与组件 text；不要 `dangerouslySetInnerHTML` 塞服务端未消毒字符串。

### SQL

```php
// 正例
$wpdb->prepare(
    "SELECT data_json FROM {$wpdb->prefix}wpcy_app_data WHERE app_id = %s AND data_key = %s",
    $app_id,
    $data_key
);

// 反例
$wpdb->query( "SELECT * FROM {$wpdb->prefix}wpcy_app_data WHERE app_id = '$app_id'" );
```

表名用 `$wpdb->prefix`，不要手写 `wp_`。

### 文件系统

不写插件目录以外。上传/缓存走 `wp_upload_dir()` 或 WP 临时目录。禁止 `file_put_contents( CHINA_YES_PLUGIN_PATH . $user_path, $data )`。

```php
// 正例
$upload = wp_upload_dir();
$path   = trailingslashit( $upload['basedir'] ) . 'wpcy/' . sanitize_file_name( $name );

// 反例
file_put_contents( ABSPATH . '../' . $_GET['file'], $body );
```

### 远程请求

只用 `wp_remote_get` / `wp_remote_post`：

- 超时 ≤ 10s
- `sslverify` = true
- 域名白名单（服务端主机表 / 签名 ruleset / 文派域后缀），用户不可自行加域名

```php
// 正例
$response = wp_remote_post(
    $url,
    [
        'timeout'   => 10,
        'sslverify' => true,
        'headers'   => [ 'X-Request-Id' => $request_id ],
        'body'      => $payload,
    ]
);
if ( is_wp_error( $response ) ) {
    return $response;
}

// 反例
wp_remote_get( $url, [ 'timeout' => 60, 'sslverify' => false ] );
curl_exec( curl_init( $user_supplied_url ) );
```

## 凭据

键：`wpcy_site_identity.binding.credential`。

- 加密存储：用 `wp_salt( 'auth' )` 派生密钥 + `sodium_crypto_secretbox`
- option `autoload = no`
- **不进** 导出、Site Health、日志、兼容性报告 / Telemetry 报文

```php
// 正例（示意，实现待 M1）
$key       = hash( 'sha256', wp_salt( 'auth' ), true );
$nonce     = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
$sealed    = sodium_crypto_secretbox( $credential, $nonce, $key );
$stored    = base64_encode( $nonce . $sealed );
update_option( 'wpcy_site_identity', $identity, false );

// 反例
update_option( 'wpcy_site_identity', [ 'credential' => $plain ], true );
error_log( 'bound with ' . $credential );
```

派生细节（KDF 轮次、密钥长度）待定（M1）；语义不可改：盐来自 `wp_salt( 'auth' )`，算法 sodium secretbox。

## 小工具容器

定稿 §7.5a-A 与 ADR-003 / `docs/specs/apps-manifest-and-bridge.md`。

| 规则 | 要求 |
|------|------|
| iframe `sandbox` | 含 `allow-scripts allow-forms`，**不含** `allow-same-origin` |
| `postMessage` | 双向校验 `event.origin` 对白名单 |
| nonce | 工具拿不到 WP nonce；桥只暴露 `wpcy/v1/apps/{app_id}/*` |
| 权限 | 按 manifest `permissions[]` 裁剪，越权即拒 |
| manifest | Ed25519 验签；签名失败不加载 |
| 来源 | HTTPS + 白名单后缀（文派域）；不给用户加源 |

```html
<!-- 正例 -->
<iframe
  src="https://apps.wenpai.net/…"
  sandbox="allow-scripts allow-forms"
  referrerpolicy="no-referrer"
></iframe>
```

```js
// 正例 · 插件页
window.addEventListener( 'message', ( event ) => {
    if ( ! allowedOrigins.has( event.origin ) ) {
        return;
    }
    // …
} );
iframe.contentWindow.postMessage( payload, appOrigin ); // 不用 '*'

// 反例
iframe.setAttribute( 'sandbox', 'allow-scripts allow-same-origin allow-forms' );
window.addEventListener( 'message', ( event ) => handle( event.data ) );
```

离线或文派域不可达：容器显示不可用，其余功能不受影响。

## 数据驻留

- `reroute` **只**对签名 ruleset 里的主机（档 A，且云桥入库 + 成功应答已就位）
- `record`（档 B）只记主机与数据类别元数据，**不记正文与查询串**
- 档 C 不拦、不记正文
- 用户不可自行加域名
- 禁止「复制一份再放行」（`docs/ops/wenpai-leaf-telemetry-reroute.md` §2）

云桥没有筛选入库接口时 **不改 URL**。

## 日志脱敏

- URL 去掉查询串
- 不记邮箱、IP、凭据、许可密钥
- `request_id` 可记
- 生产默认 warning 及以上（[coding-standards.md](coding-standards.md)）

```php
// 正例
$logger->warning( 'upstream timeout', [
    'host'       => wp_parse_url( $url, PHP_URL_HOST ),
    'path'       => wp_parse_url( $url, PHP_URL_PATH ),
    'request_id' => $request_id,
] );

// 反例
$logger->warning( 'timeout ' . $url, [ 'email' => $admin_email, 'ip' => $_SERVER['REMOTE_ADDR'] ] );
```

## 上报字段边界

兼容性报告（自采）与改道入库是两张面。云桥 **不进库** 表以 linuxjoy `docs/ops/wenpai-leaf-telemetry-reroute.md` §5 为准：

| 类 | 例子（不进库） |
|----|----------------|
| 评论与顾客联系 | 评论正文、作者、顾客邮箱/电话；Akismet 评论包 |
| 功能密钥 | 许可密钥、Jetpack 握手、Woo.com helper token |
| 精确地理 | 管理员 IP、经纬度、商店省/州、邮编 |
| 订单正文 | 近 20 单抽样（行项目、顾客侧内容） |
| Automattic 侧身份 | `store_id`、Jetpack `blog_id` |
| 凭据与正文 | 用户密码、文章正文 |

自采（Telemetry 模块）另禁：订单正文、顾客联系方式、管理员邮箱、精确地理。`site_url` 明文是合规需要，不是漏洞。

## 依赖

- `composer audit`、`npm audit` 进 CI（待 M1 加 job；本任务不改 workflow）
- 第三方库进发布包前审许可与体积
- 现状生产 Composer 依赖只有 `yahnis-elsts/plugin-update-checker`；4.0 更新通道迁走后是否仍需要，待定（M1）

## 漏洞响应

- 报告渠道：wpcy.com `/support` —— **待定（M0）**（是否另开安全邮箱未拍板）
- 72 小时内响应（确认收到 + 是否受理）
- 补丁走两条线：`4.x.y` 与 `3.9.z`（3.9.x 只修安全，流程见 [release.md](release.md)）
