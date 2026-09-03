# ADR-003：WP-China-Yes 4.0 小工具容器

状态：已定 2026-09-03  
日期：2026-09-03  
来源：linuxjoy 定稿 §7.5a-A

## 背景

文派生态有一批免费 / 付费 HTML 网页服务与小工具。产品意图是把它们集成进插件，类比微信小程序：引流、付费用户在自己站里用、数据存自己站。新增或更新工具不应要求发插件版本。

4.0 首发模块为 `Services/Apps`，入口在「文派服务」页。合同见 `docs/specs/apps-manifest-and-bridge.md`。

## 决策

小工具 = 文派托管 HTML 页面。

- 在「文派服务」页内以 `<iframe sandbox="allow-scripts allow-forms" referrerpolicy="strict-origin">` 加载。
- 来源白名单只含文派域。初始 `apps.wpcy.com`；可由签名 manifest 索引扩展，但必须 HTTPS，且主机后缀在白名单 `.wpcy.com` / `.wenpai.net` 内。
- 每个工具一份服务端签名的 manifest：`id`、`name`、`icon`、`entry_url`、`permissions[]`、`entitlement`、`version`、`min_plugin_version` 等。完整字段见规格。
- manifest 使用 Ed25519 签名。公钥随插件发布。验签用 `sodium_crypto_sign_verify_detached`（PHP ≥ 7.2 自带 sodium）。
- 桥接：iframe ↔ 宿主用 `postMessage`；宿主再转发 REST `wpcy/v1/apps/*`。权限按 manifest `permissions[]` 裁剪，未声明即拒。
- 数据落用户站点表 `{prefix}wpcy_app_data`（`app_id`、`data_key`、`data_json`、`updated_at`），按 app 命名空间隔离。
- 权益驱动可见性与可用性：免费工具对绑定站点可见；付费工具无权益时显示介绍 +「获取」→ `/go/{service}`；权益到期工具只读、数据仍在。
- 新增 / 更新工具不发插件版本。

## 约束

- iframe 不得 `allow-same-origin`。
- 桥接消息双向校验 `event.origin` 与 manifest `entry_url` 的 origin 一致。
- 工具永远拿不到 REST nonce（由宿主页转发）。
- 权限按 manifest `permissions[]` 裁剪，未声明即拒。
- 每个工具的数据 key 数量与总大小有上限（建议 500 key / 5MB，**待定（M0）**：由产品负责人与实现方在 M0 定死写入 `Apps/DataStore`）。
- 文派域不可达时容器显示不可用，其余功能不受影响。
- 诊断页可按工具导出 / 删除数据。
- 卸载插件时用户选择是否删除 `wpcy_app_data`。

## 不采用的方案

### 把工具 JS 下载到本地执行

否决（4.0）。版本管理与安全面更大，留 4.x 评估。

### 打开新标签跳到文派站

否决。不是「在自己站里用」，数据也不在用户站。

### WP Abilities API

4.x 可对接，4.0 不依赖。对接方式：把每个工具 manifest 的 `permissions[]` 映射成 `wpcy/*` abilities，供 MCP Adapter、Command Palette 与未来 Core agent 发现（WP 7.0 已有 Client-Side Abilities，MCP Adapter 0.6+ 要求 WP 6.9+）。

### OpenStation（`desktop-mode` 插件）的窗口模型 / App Framework

不采用为依赖或蓝本。核实（`linuxjoy docs/research/2026-09-03-wordpress-admin-desktop-and-new-apis.md` §1、§4.2）：OpenStation 是 Automattic 维护、用户 opt-in 的 wp-admin 多窗口壳，**不是 Core**；其窗口是同域 chromeless iframe + 类型化 postMessage，App Framework 是 PHP 声明的服务端渲染窗口且标 Experimental。与本 ADR 的差别在内容来源（同域后台页 vs 跨域文派托管页）、隔离模型（chromeless vs `sandbox=`）、声明方式（`openstation_register_window()` vs 服务端签名 manifest）、分发（改插件 vs 改托管页）。**兼容**：用户开启 OpenStation 时叶子服务页成为一扇窗口，容器内的沙箱 iframe 照常工作（双层 iframe）。**不为像它而改 manifest，不注册 OpenStation 窗口作为 4.0 前提。**

### Interactivity API / Script Modules 作为小工具运行时

否决。二者面向前台区块（`data-wp-*` 指令、ESM import map），不是跨域后台 iframe 的容器；小工具若将来改为同域 block 再议。

## 后果

正面：

- 工具迭代不发版。
- 统一入口（文派服务页）。
- 数据主权在用户站。

代价：

- 多一层桥接安全面（origin、权限、nonce 隔离）。
- 需要签名基础设施（Ed25519 公钥随插件发布、索引与单工具双重验签）。
- 需要合同测试（mock manifest 与 mock 工具页）。

## 验收

- mock manifest 验签通过 / 失败两条路径。
- 越权消息被拒。
- 跨 origin 消息被丢弃。
- 数据读写隔离（工具 A 读不到工具 B）。
- 离线时容器降级不影响其它页。
