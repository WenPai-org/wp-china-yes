# 文案与本地化规则

这份文档解决什么问题：面向用户的字符串曾经不进词表、夹杂英文错误串、出现「遥测 / 隐私 / 上报」这类界面不该露出的词。本文规定：每个用户可见字符串先登记再进代码、语气与错误句式、英文只留专有名词、PHP 与 JS 的 i18n 写法。

相关：[`admin-ui-spec.md`](../design/admin-ui-spec.md) §4、[`design-sop.md`](design-sop.md)、[`coding-standards.md`](coding-standards.md) i18n 节。

## 词表

- 面向用户的每个字符串在 [`admin-ui-spec.md`](../design/admin-ui-spec.md) §4 词表登记。
- 新增词**先进词表再进代码**。代码审查若发现用户可见字符串不在词表且本次 diff 也没改 §4，阻断。
- 禁用词（界面不出现）：遥测、隐私、上报、匿名数据、opt-in、entitlement、SaaS、套餐（用「权益」）、Pro。产品定稿：报告常开，界面不露出。
- 状态词与视觉（圆点 / Notice）以 §4 表为准，例如「国内镜像正常」绿点、「已回原始上游」琥珀点、「不可用」红点、「暂时无法连接文派服务」琥珀 Notice。

## 语气

- 说「为你做了什么」，不说术语。对例：「国内镜像正常」。错例：「mirror fallback 已触发」。
- 短句。
- 不用感叹号。
- 不出现「遥测/隐私/上报/匿名数据」。
- 说明文字只伴随异常出现，正常项不解释自己（规格 §1）。

## 错误文案模板

```text
暂时无法 <做什么>，<下一步>
```

对例：

- `暂时无法连接文派服务，请稍后重试。`
- `暂时无法保存设置，请检查网络后重试。`
- `暂时无法运行连接检查，请稍后重试。`

「暂时无法连接文派服务」已在 §4 词表。若要补「下一步」半句，先改词表再改代码。

不要把 REST 错误码、PHP exception、英文 `Site binding is not available.` 直接画到界面上。那是反面教材第 2 条。

## 英文

英文只保留专有名词：WordPress、Windfonts、Cravatar。其余用户可见文字用简体中文。

WeAvatar 若出现在头像选项里，按专有名词保留（与规格 §3.2 头像枚举一致）。

## i18n 写法

text domain **固定**为 `wp-china-yes`（与插件头一致，见 [`coding-standards.md`](coding-standards.md)）。所有面向用户的字符串走 `__()`（或 `esc_html__()` / `esc_attr__()` / `_n()` 等同类函数），禁止裸字符串进界面。

### PHP

```php
echo '<h1>' . esc_html__( '文派叶子 · 恢复模式', 'wp-china-yes' ) . '</h1>';

echo '<p>' . esc_html__(
	'如果后台样式错乱或站点无法访问，可在此一键停用所有 URL 改写与模块。此页不依赖 JavaScript。',
	'wp-china-yes'
) . '</p>';

$title = __( '关闭全部 URL 改写', 'wp-china-yes' );
```

恢复页原文见 `src/Admin/RecoveryPage.php`。错误句同样包起来：

```php
return new WP_Error(
	'wpcy_binding_unavailable',
	__( '暂时无法连接文派服务，请稍后重试。', 'wp-china-yes' ),
	array( 'status' => 503 )
);
```

（上例是写法示范。改真实错误串前先登记词表；当前实现若仍是英文，属规格差距，不在本文改 `src/`。）

### JavaScript

```js
import { __ } from '@wordpress/i18n';

<PageShell title={ __( '概览', 'wp-china-yes' ) }>
	<StatusDot tone="success" label={ __( '国内镜像正常', 'wp-china-yes' ) } />
	<Notice status="warning" isDismissible={ false }>
		{ __( '暂时无法连接文派服务', 'wp-china-yes' ) }
	</Notice>
</PageShell>
```

JS 与 PHP 用同一 text domain、同一中文原文。不要在 JS 里写英文再指望翻译平台补中文来「先过验收」。

## 不做什么

- 不把内部标识（`entitlement`、`mirror fallback`、option 键名）画到界面上。
- 不写用户可见的「遥测」「匿名数据」「隐私开关」「上报」。
- 不在代码里先写字符串、事后补词表。
- 不把 WP-CLI 的 JSON 字段名当作用户文案规则；CLI 不走本文件，但也不准输出禁用词。
- 不在本文修改 `src/` 里已有的英文错误串；那是实现任务，对照词表另开任务书。
