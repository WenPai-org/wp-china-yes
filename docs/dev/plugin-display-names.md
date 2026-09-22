状态：定案 · 2026-09-22

# 文派插件显示名

三层显示名，不要并成一个词。

3.x 的 OEM 管理后台（藏菜单、换叶子自己的壳）仍不进 4.0 首发，见 [`docs/4.0-rewrite-plan.md`](../4.0-rewrite-plan.md) §4.3。本文的白标只改品牌名；页脚插件名留了口；功能名不改。不把那套 OEM 加回来。

## 三层

| 层 | 作用 | WPSlug 现在的默认 | 白标 |
|----|------|-------------------|------|
| 功能名 | 左侧菜单。停在该插件原来的菜单位置 | 英文源串 `Slug`，已有中文译文「别名」。素格、别名、Slug 是同一个功能名 | 不改 |
| 品牌名 | 设置页顶栏左侧 | 「文派素格」 | 改 |
| 插件名 | 页脚，可带版本号 | `WPSlug` | 可改显示。默认可先只改品牌。页脚插件名也留了口 |

以后 WPCY 自己有一级菜单时，各插件再作为子菜单挂上去，并可以显示品牌。在那之前，不要各自 `add_menu_page` 占 80–82。

智储今天的左侧仍是「文派智储」（位置 81）。这一版不改。功能名还没定，不要写成 S3 Storage。

## 共同过滤器

一只过滤器，WPCY 只挂一次。功能名不在这个数组里。下面是目标契约。

```php
$display = apply_filters('wenpai_plugin_display', array(
    'brand' => '文派素格',
    'plugin_name' => 'WPSlug',
    'help_url' => 'https://wpcy.com/slug',
    'feedback_url' => 'https://wpcy.com/support',
), 'wpslug');
```

第二个参数是插件 id。用户没改的键保持默认。用户设了品牌名之后，不再对这个自定义字符串做翻译。

WPSlug 已经先有 `wpslug_brand_name` 和 `wpslug_plugin_name`。那是第一只的口；后续收成 `wenpai_plugin_display`。这次不改 WPSlug 的 PHP。

## WPCY 后台设置

只写进文档，这次不写设置页 PHP。

- 帮助、反馈地址：在 WPCY 插件后台用一组设置统一替换，经过 `help_url` / `feedback_url` 作用到各插件。
- 品牌名：同一处设置，让用户把自己的品牌名套到各插件顶栏（`brand`）。
- 不动：选项 key、文本域、Update URI（仍 `https://updates.wenpai.net`）、插件目录名、遥测改道。

## 国际化

1.3 新壳很多是中文源串，英文站点仍显示中文。翻译是稳定发版之后的小版本，不在这份白标文档里开工。
