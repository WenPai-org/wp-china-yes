=== WPCY.COM ===
Contributors: wenpai
Tags: china, performance, mirror, gravatar, cdn
Requires at least: 4.9
Tested up to: 7.1
Requires PHP: 7.4.0
Stable tag: 3.9.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WordPress 中国连接与公共资源加速工具，提供镜像故障回源、Cravatar 和连接诊断能力。

== Description ==

WPCY.COM 改善中国大陆 WordPress 站点访问 WordPress.org、Gravatar 和常用公共前端资源时的连接体验。

3.9.3 是稳定性版本：镜像不可用时保留原始上游地址；已移除会错误改写站点自身 wp-content 资源的“前台加速”。

== Installation ==

1. 从项目 Release 下载完整 ZIP；源码归档不包含 Composer 依赖，不能直接安装。
2. 在 WordPress 后台上传并启用。
3. 在“设置 → WPCY.COM”中选择需要的连接和公共资源加速能力。

== Changelog ==

= 3.9.3 =

* 镜像故障时自动回到原始上游。
* 移除错误的站点自身资源公共 CDN 重写。
* 修复设置损坏、服务重复初始化、权限检查和 PHP 版本声明。
