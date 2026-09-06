"""v3 部件：更多图标、hero、生态块。由 build.py import。"""

MORE_ICONS = {
    "font": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20 11 4h2l7 16M7 14h10"/></svg>',
    "leaf": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19c0-7 4-14 12-15h2v2c0 8-5 13-13 13z"/><path d="M8 16 16 8"/></svg>',
    "book": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19a2 2 0 0 1 2-2h13"/></svg>',
    "chat": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H9l-5 4z"/></svg>',
    "video": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/></svg>',
    "help": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/></svg>',
    "rss": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>',
    "wechat": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4C5.7 4 3 6.2 3 9c0 1.6.9 3 2.2 4L4.5 15.5 7 14.3c.6.2 1.3.3 2 .3"/><path d="M15 9c3.3 0 6 2.2 6 5 0 1.6-.9 3-2.2 4l.7 2.5-2.5-1.2c-.6.2-1.3.3-2 .3-3.3 0-6-2.2-6-5s2.7-5 6-5z"/></svg>',
    "store": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16l-1 11H5z"/><path d="M4 9 6 4h12l2 5"/><path d="M9 13v3M15 13v3"/></svg>',
    "sparkle": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/></svg>',
    "layers": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/></svg>',
    "settings": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/></svg>',
    "home": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10"/></svg>',
    "pulse": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-7 4 14 3-7h4"/></svg>',
    "external": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M19 14v5H5V5h5"/></svg>',
    "sliders": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v16M12 4v16M18 4v16"/><circle cx="6" cy="10" r="2" fill="#fff"/><circle cx="12" cy="15" r="2" fill="#fff"/><circle cx="18" cy="8" r="2" fill="#fff"/></svg>',
    "image": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m21 16-5-5-9 8"/></svg>',
}


def hero(ICON, eyebrow, pill, title, lede, actions, feats):
    f = "".join(
        f'<div class="f"><div class="tile {tone}">{ICON[i]}</div><div><div class="t">{t}<span class="dot {tone}"></span></div><div class="d">{d}</div></div></div>'
        for i, tone, t, d in feats
    )
    return (
        f'<section class="card hero"><div class="hero-main"><div class="hero-eyebrow">{ICON["leaf"]}{eyebrow}'
        f'<span class="pill {pill[0]}">{pill[1]}</span></div><h2>{title}</h2><p>{lede}</p>'
        f'<div class="actions">{actions}</div></div><div class="hero-side">{f}</div></section>'
    )


def eco(ICON, crossborder=False):
    def links(items):
        return '<ul class="links">' + "".join(f'<li><a href="#">{ICON[i]}{t}</a></li>' for i, t in items) + "</ul>"

    c1 = (
        f'<article class="card"><div class="tile accent">{ICON["layers"]}</div><h3>文派开源生态</h3>'
        f'<p>叶子只是入口。文派维护着 WordPress 中文更新源、公共库节点、Cravatar 头像与 Windfonts 字体，全部免费。</p>'
        + links([("globe", "文派开源 WenPai.org"), ("download", "WordPress 中文更新源"), ("user", "Cravatar 头像"), ("font", "Windfonts 中文字体")])
        + "</article>"
    )
    c2 = (
        f'<article class="card"><div class="tile ok">{ICON["rss"]}</div><h3>文派茶馆</h3>'
        f'<p>关注公众号或订阅通讯，获取 WordPress 中文圈的更新、提示与独家内容。</p>'
        f'<div class="qr"><div><span></span><small>公众号</small></div><div><span></span><small>视频号</small></div></div></article>'
    )
    if crossborder:
        c3 = (
            f'<article class="card"><div class="tile warn">{ICON["store"]}</div><h3>跨境店工具</h3>'
            f'<p>面向中国团队运营的海外 WooCommerce 店：收中国买家的钱、把订单推到微信。</p>'
            + links([("wechat", "微信支付 for WooCommerce · 了解 →"), ("chat", "订单微信通知 · 了解 →"), ("help", "跨境建站指南")])
            + "</article>"
        )
    else:
        c3 = (
            f'<article class="card"><div class="tile">{ICON["help"]}</div><h3>浏览更多</h3>'
            f'<p>文档、社区与视频，遇到问题先从这里找。</p>'
            + links([("external", "文派叶子官网 WPCY.com"), ("book", "快速入门指南"), ("chat", "支持论坛"), ("video", "Bilibili 官方频道"), ("help", "常见问题与疑难排查")])
            + "</article>"
        )
    return f'<div class="eco">{c1}{c2}{c3}</div>'
