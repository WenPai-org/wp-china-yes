# Changelog

All notable changes to `WP-China-Yes` will be documented in this file.

## v3.8 - 2025-02-05

* 文派叶子 v3.8 重大更新！全新UI 设计更接近 WordPress 原生体验。

1. 替换业务域名 WP-China-Yes.com 为新域名 WPCY.COM ；
2. 修复 adminCDN 支持 jsDelivr 加速无效等问题；
3. 新增 Bootstrap CDN 转接至 adminCDN 加速支持；
4. 新增 Windfonts 中文排版优化：支持段首空格 2em；
5. 新增 Windfonts 中文排版优化：支持文本内容对齐；
6. 新增 [脉云维护] 菜单并支持WP系统状态监控，可在页脚位置显示内存、CPU用量等信息；
7. 新增 [欢迎使用] 用户引导页面，更清晰的功能指导和简介。
8. 新增 [建站工具] 文派·寻鹿建站套件展示页面，内容待完善。
9. 优化 [萌芽加速] 设置，与 WordPress 程序端加速选项分离便于添加后续项目；
10. 优化 [关于插件] 页面更简约的赞助商 Logo 和贡献者名单展示。
11. 补充 changelog.txt 文本文件，跟随插件副本分发。
12. 补充 copyright.txt 版权文件，跟随插件副本分发。

## v3.7.1 - 2024-11-19

1. 性能优化
2. 修复监控无法关闭的问题

**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/v3.6.5...v3.7.1

## v3.6.5 - 2024-08-23

1. 优化 CLI 判断
2. 回退替换钩子修改

**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/v3.6.4...v3.6.5

## v3.6.4 - 2024-08-23

1. WP-CLI 下不运行 adminCDN 部分，防止影响缓冲区。
2. 部分文案调整支持多语言。

**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/v3.6.3...v3.6.4

## v3.6.3 - 2024-08-23

1. 为自动监控功能添加开关
2. adminCDN 支持 jsDelivr 加速
3. Windfonts 支持优化模式开关

**adminCDN 的 jsd 加速默认屏蔽 gh 端点，如有主题插件作者需要使用请联系加白。**
**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/v3.6.2...v3.6.3

## v3.6.2 - 2024-03-09

1. UI 重构
2. 修复市场加速导致 Beta 测试插件失效的问题
3. 更新接口及测试 URL

**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/v3.6.1...v3.6.2

## v3.6.1 - 2024-03-08

1. 重构全部代码
2. 应用市场调整为[文派开源](https://wenpai.org)
3. 加速服务调整为[萌芽加速](https://admincdn.com)
4. 头像调整为[初认头像](https://cravatar.com)
5. 支持自动检测节点可用性

**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/V3.5.5...v3.6.1
