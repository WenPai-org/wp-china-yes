"""v5 部件：F 结构 × E 配色。可折叠白色 hero、面积图统计卡、线路列表、时间线。"""


def area(points, w=300, h=44, color="#3858e9"):
    n = len(points); mx = max(points) or 1
    xs = [i * (w / (n - 1)) for i in range(n)]
    ys = [h - 6 - (p / mx) * (h - 12) for p in points]
    d = "M" + " L".join(f"{x:.1f},{y:.1f}" for x, y in zip(xs, ys))
    fill = d + f" L{w},{h} L0,{h} Z"
    gid = "g" + str(abs(hash(tuple(points))) % 99999)
    return (f'<svg class="area" viewBox="0 0 {w} {h}" preserveAspectRatio="none" aria-hidden="true">'
            f'<defs><linearGradient id="{gid}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{color}" stop-opacity=".18"/><stop offset="1" stop-color="{color}" stop-opacity="0"/></linearGradient></defs>'
            f'<path d="{fill}" fill="url(#{gid})"/><path d="{d}" fill="none" stroke="{color}" stroke-width="1.75" stroke-linejoin="round"/></svg>')


def hero_white(ICON, eyebrow, title, lede, actions, ring, facts, summary, tone="ok"):
    """可折叠：展开时无摘要栏，右上角"收起"；收起后一条 56px 摘要栏。状态存 localStorage（原型）。
    tone: ok / warn / mute —— 同时决定健康环颜色与摘要栏 pill。"""
    f = "".join(f'<span>{k} <b>{v}</b></span>' for k, v in facts)
    pill, rest = summary
    sf = "".join(f'<span class="sf">{x}</span>' for x in rest)
    ring_cls = "ring" if tone == "ok" else f"ring {tone}"
    pill_cls = "pill ok" if tone == "ok" else ("pill warn" if tone == "warn" else "pill")
    return f'''<section class="card hero-w is-open" data-hero>
  <button class="hero-collapse" type="button" data-hero-toggle aria-label="收起">{ICON["chevron"]}<span>收起</span></button>
  <div class="hero-body">
    <div class="hero-l">
      <div class="eyebrow">{eyebrow}</div>
      <h1>{title}</h1>
      <p>{lede}</p>
      <div class="cta">{actions}</div>
    </div>
    <div class="hero-r">
      <div class="{ring_cls}" style="--pct:{ring[0]}"><div><div class="n">{ring[1]}</div><div class="l">{ring[2]}</div></div></div>
      <div class="facts">{f}</div>
    </div>
  </div>
  <button class="hero-bar" type="button" data-hero-toggle>
    <span class="{pill_cls}">{pill}</span>{sf}<span class="hero-bar-more">{ICON["chevron"]}展开</span>
  </button>
</section>
<script>(function(){{var h=document.querySelector("[data-hero]");if(!h)return;var k="wpcy-hero-open";if(localStorage.getItem(k)==="0")h.classList.remove("is-open");h.querySelectorAll("[data-hero-toggle]").forEach(function(b){{b.addEventListener("click",function(){{h.classList.toggle("is-open");localStorage.setItem(k,h.classList.contains("is-open")?"1":"0");}});}});}})();</script>'''


def stat_area(ICON, icon, k, v, unit, delta, d, pts):
    """pts 为空 → 空态：数字位显示"还没有数据"，不画图。"""
    if not pts:
        return f'<article class="card stat-a"><div class="k">{ICON[icon]}{k}</div><div class="v none">{v}</div><div class="d">{d}</div></article>'
    dl = f'<span class="delta">{ICON["up"]}{delta}</span>' if delta else ""
    return f'<article class="card stat-a"><div class="k">{ICON[icon]}{k}</div><div class="v">{v}<small>{unit}</small>{dl}</div><div class="d">{d}</div>{area(pts)}</article>'


def routes_list(rows):
    li = "".join(f'<li><span class="rdot {t}"></span><div><div class="t">{a}</div><div class="d">{b}</div></div><span class="ms">{c}</span><span class="ago">{e}</span></li>' for t, a, b, c, e in rows)
    return f'<ul class="routes">{li}</ul>'


def timeline(items):
    li = "".join(f'<li class="{t}"><time>{a}</time><div class="e">{b}</div><div class="d">{c}</div></li>' for t, a, b, c in items)
    return f'<ul class="tl">{li}</ul>'


def resources_compact(ICON, crossborder=False):
    def group(label, items):
        chips = "".join(f'<a class="chip" href="#">{ICON[i]}{t}</a>' for i, t in items)
        return f'<div class="rgrp"><span class="rlabel">{label}</span>{chips}</div>'
    groups = [
        group("文派开源", [("layers", "WenPai.org"), ("download", "中文更新源"), ("user", "Cravatar"), ("font", "Windfonts")]),
        group("学习与支持", [("book", "入门指南"), ("help", "常见问题"), ("chat", "支持论坛"), ("video", "Bilibili")]),
        group("文派茶馆", [("wechat", "公众号"), ("rss", "订阅通讯"), ("external", "WPCY.com")]),
    ]
    if crossborder:
        groups.append(group("跨境店工具", [("wechat", "微信支付 for WooCommerce"), ("chat", "订单微信通知"), ("store", "跨境建站指南")]))
    return (f'<section class="resources2"><div class="r2-head"><h2>来自文派</h2><p>叶子只是入口。文派维护着中文更新源、公共库节点、Cravatar 与 Windfonts，全部免费。</p></div>'
            f'<div class="r2-body">{"".join(groups)}</div></section>')
