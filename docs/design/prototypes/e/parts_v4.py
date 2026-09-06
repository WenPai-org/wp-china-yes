"""v4 部件：页头式 hero、状态带、合并统计、小节标题、资源带。"""


def pagehero(ICON, eyebrow, title, lede, actions):
    return (
        f'<div class="pagehero"><div><div class="eyebrow">{ICON["leaf"]}{eyebrow}</div>'
        f'<h1>{title}</h1><p>{lede}</p></div><div class="actions">{actions}</div></div>'
    )


def strip(ICON, items):
    # items: (icon, label, value, tone)
    return '<div class="strip">' + "".join(
        f'<div>{ICON[i]}<div><div class="k">{k}</div><div class="v"><span class="dot {tone}"></span>{v}</div></div></div>'
        for i, k, v, tone in items
    ) + "</div>"


def sec(title, sub=None, more=None):
    s = f'<p>{sub}</p>' if sub else ""
    m = f'<a href="{more[1]}">{more[0]}</a>' if more else ""
    right = f'<div class="sec-r">{s}{m}</div>' if (s or m) else ""
    return f'<div class="sec-h"><h2>{title}</h2>{right}</div>'


def stats(ICON, items):
    # items: (icon, label, value, unit, desc, bars)
    out = []
    for i, k, v, unit, d, bars in items:
        b = "".join(f'<i style="height:{h}%" class="{"hi" if n == len(bars) - 1 else ""}"></i>' for n, h in enumerate(bars))
        out.append(f'<div><div class="k">{ICON[i]}{k}</div><div class="v">{v}<small>{unit}</small></div><div class="d">{d}</div><div class="bar" aria-hidden="true">{b}</div></div>')
    return '<section class="card stats">' + "".join(out) + "</section>"


def resources(ICON, crossborder=False):
    def col(icon, title, desc, links, promo=False):
        li = "".join(f'<li><a href="#">{t}{ICON["external"]}</a></li>' for t in links)
        return f'<div class="res{" promo" if promo else ""}"><h3>{ICON[icon]}{title}</h3><p>{desc}</p><ul>{li}</ul></div>'

    cols = [
        col("layers", "文派开源", "叶子只是入口，这些都免费。", ["文派开源 WenPai.org", "WordPress 中文更新源", "Cravatar 头像", "Windfonts 中文字体"]),
        col("book", "学习与支持", "遇到问题先从这里找。", ["快速入门指南", "常见问题与疑难排查", "支持论坛", "Bilibili 官方频道"]),
        col("rss", "文派茶馆", "WordPress 中文圈的更新与独家内容。", ["关注公众号", "订阅通讯", "官网 WPCY.com"]),
    ]
    if crossborder:
        cols.append(col("store", "跨境店工具", "给中国团队运营的海外 WooCommerce 店。", ["微信支付 for WooCommerce · 了解 →", "订单微信通知 · 了解 →", "跨境建站指南"], promo=True))
    grid = "cols-4" if crossborder else ""
    return f'<section class="resources">{sec("来自文派", "资源、社区与延伸服务")}<div class="res-grid {grid}">{"".join(cols)}</div></section>'
