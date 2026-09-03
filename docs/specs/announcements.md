# 公告模块

状态：草案（M0）· 来源：linuxjoy 定稿 §7.5a / §7.1a / §7.1c

本文冻结 4.0 概览页「公告」模块的源、格式、缓存与关闭状态。模块 `Admin/Announcements`。不得在本文新增产品决定。

## 源

`GET https://wpcy.com/wp-json/wpcy/v1/announcements`

由 wpcy.com 侧的 `wpcy-site` 插件聚合 wptea.com 与 one.weixiaoduo.com 的 feed。**插件不直接解析 RSS**。

不可加源、不可改源。无营销弹窗。不在其它页面出现（仅概览页该模块）。

## 格式

```json
{
  "generated_at": "2026-09-03T12:00:00Z",
  "items": [
    {
      "id": "wptea-12345",
      "source": "wptea",
      "title": "…",
      "url": "https://wptea.com/…",
      "summary": "…",
      "published_at": "2026-09-03T08:00:00Z"
    }
  ]
}
```

| 字段 | 规则 |
|---|---|
| `generated_at` | UTC ISO 8601 |
| `items` | 最多返回 20 条 |
| `id` | 稳定字符串，关闭状态按此键 |
| `source` | `wptea` \| `one` |
| `title` | 纯文本 |
| `url` | HTTPS |
| `summary` | ≤ 200 字 |
| `published_at` | UTC ISO 8601 |

插件展示前 5 条未关闭的（按 `published_at` 新到旧）。超过 5 条未关闭的其余条不渲染，关闭其中一条后顺延。

## 缓存

- transient `wpcy_announcements`，24h。
- 每日拉取一次。失败保留旧缓存。
- 无缓存时模块不渲染（不显示错误）。

## 关闭

关闭状态存 `wpcy_settings.announcements.dismissed`（id 列表，最多保留 100 个）。超出时丢掉最旧的 id。

关闭经 REST `POST /wpcy/v1/announcements/{id}/dismiss`（`manage_options` + nonce）。未知 id 仍接受（幂等）。

## 界面

仅概览页。无营销弹窗。不在连接优化、文派服务、诊断、恢复页出现。
