=== WP-China-Yes ===
Contributors: sunxiyuan
Donate link: https://www.ibadboy.net/archives/3204.html
Tags: China Super, 429, WP China Yes, wp-china-yes, WP-China-Yes
Requires at least: 4.5
Tested up to: 5.4.0
Requires PHP: 5.6
Stable tag: 2.1.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

这是一个颠覆性的插件，她将全面改善中国大陆站点在访问WP官方服务时的用户体验，其原理是将位于国外的官方仓库源替换为由社区志愿者维护的国内源，以此达到加速访问的目的。

== Description ==

因为WordPress官方的服务器都在国外，所以中国大陆的用户在访问由WordPress官方提供的服务（插件、主题商城，WP程序版本更新等）时总是很缓慢。
近期又因为被攻击的原因，WordPress的CDN提供商屏蔽了中国大陆的流量，导致大陆用户访问插件主题商城等服务时报429错误。
为解决上述问题，我开发了WP-China-Yes插件，该插件可以将WP站点访问官方服务的一切流量迁移到由社区志愿者维护的大陆源上，从而全面优化用户体验。

== Frequently Asked Questions ==

= 速度为什么这么慢 =

加速节点使用CDN缓存数据，对于访问人数较少的冷门资源访问速度会慢很多。若遇到访问超时的情况请等10分钟再试，这段时间CDN会自动去WordPress官方服务器拉取资源供使用。
另外也请检查一下服务器的DNS配置是否正确，之前出现过用户设置dns为1.1.1.1导致无法解析仓库源域名的情况

= 为什么更新完还会再次要求更新 =

一些开发者在发布新版本的时候没有标记版本tag，导致CDN返回的还是旧版本。目前我们维护了一个此类插件的“名单”，名单中的插件如有更新通常会在30秒内全网刷新缓存，若你的插件依旧无法更新可能是未被加入到“名单”中，遇到这种情况还请及时与我联系。

== Changelog ==

= 2.1.0 =
* 取消社区源选择功能，只保留主源和备源
* 去除设置页的tab
* 在仪表盘放置了赞助者名单展示小部件（可关闭）
* 修复修改仓库源后刷新页面无法正确展示源信息的问题
* 修复API接口编写不规范的问题
* 修复插件无法运行在使用子目录部署的WordPress上的问题

= 2.0.0 =
* 社区源列表
* 自定义源支持

= 1.0.1 =
* 基本功能