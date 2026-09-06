"""v6 部件（2026-09-06 夜，按 feibisi 反馈与决定 D1–D3）：
- hero_stack：可折叠白 hero，右侧是"核心服务连通栈"（取代分数环）。
- routes_list：线路列表每行带服务商。
- eco_block："来自文派"整块（取代一排小按钮）。
"""

STATUS = {  # 状态词 → (pill 类, 界面词)。与决定 D2 表一一对应。
    "on":   ("ok", "已接通"),
    "direct": ("", "直连"),
    "fallback": ("warn", "已回退"),
    "off":  ("", "未启用"),
    "down": ("bad", "不可达"),
    "paused": ("", "已停用"),
}


def svc_stack(ICON, rows):
    """rows: (status_key, icon, name, provider_line, extra, action_html)
    provider_line 例："经 WenPai.org 接通" / "直连 WordPress.org" / "adminCDN 暂时不可达，已回原始源"。
    extra 例："只在后台" / "" ；action_html 例：启用链接。"""
    out = []
    for key, icon, name, prov, extra, action in rows:
        cls, word = STATUS[key]
        tone = {"ok": "ok", "warn": "warn", "bad": "bad"}.get(cls, "")
        ex = f'<span class="scope">{extra}</span>' if extra else ""
        act = action or ""
        out.append(f'<li class="{key}"><div class="tile {tone}">{ICON[icon]}</div>'
                   f'<div class="svc-t"><div class="n">{name}{ex}</div><div class="p">{prov}</div></div>'
                   f'<div class="svc-r"><span class="pill {cls}">{word}</span>{act}</div></li>')
    return f'<ul class="svc-stack">{"".join(out)}</ul>'


def hero_stack(ICON, eyebrow, title, lede, actions, stack_rows, summary, tone="ok", stack_title="核心服务"):
    """展开：左标语 + 右连通栈；收起：一条摘要栏。状态存 localStorage（原型）。"""
    pill, rest = summary
    sf = "".join(f'<span class="sf">{x}</span>' for x in rest)
    pill_cls = "pill ok" if tone == "ok" else ("pill warn" if tone == "warn" else "pill")
    return f'''<section class="card hero-w is-open" data-hero>
  <button class="hero-collapse" type="button" data-hero-toggle aria-label="收起">{ICON["chevron"]}<span>收起</span></button>
  <div class="hero-body hero-body-v6">
    <div class="hero-l">
      <div class="eyebrow">{eyebrow}</div>
      <h1>{title}</h1>
      <p>{lede}</p>
      <div class="cta">{actions}</div>
    </div>
    <div class="hero-svc">
      <div class="hero-svc-h">{stack_title}</div>
      {svc_stack(ICON, stack_rows)}
    </div>
  </div>
  <button class="hero-bar" type="button" data-hero-toggle>
    <span class="{pill_cls}">{pill}</span>{sf}<span class="hero-bar-more">{ICON["chevron"]}展开</span>
  </button>
</section>
<script>(function(){{var h=document.querySelector("[data-hero]");if(!h)return;var k="wpcy-hero-open";if(localStorage.getItem(k)==="0")h.classList.remove("is-open");h.querySelectorAll("[data-hero-toggle]").forEach(function(b){{b.addEventListener("click",function(){{h.classList.toggle("is-open");localStorage.setItem(k,h.classList.contains("is-open")?"1":"0");}});}});}})();</script>'''


def routes_list(rows):
    """rows: (tone, name, provider, desc, ms, ago)。服务商紧跟名称后的小标签。"""
    li = "".join(
        f'<li><span class="rdot {t}"></span><div><div class="t">{a}<span class="prov">{p}</span></div><div class="d">{b}</div></div>'
        f'<span class="ms">{c}</span><span class="ago">{e}</span></li>'
        for t, a, p, b, c, e in rows)
    return f'<ul class="routes">{li}</ul>'


def eco_block(ICON, crossborder=False):
    """来自文派：一个带浅色底的整块。左：定位一句 + 一个链接；右：按品牌分栏，每栏 名称 / 一行说明 / 一个链接。链接总数 ≤ 8。"""
    brands = [
        ("download", "WenPai.org", "WordPress.org 更新与安装包的国内镜像，文派开源维护。", "wenpai.org"),
        ("bolt", "adminCDN", "Google Fonts、Ajax、jsDelivr、CDNJS、Emoji 的国内节点。", "admincdn.com"),
        ("user", "Cravatar", "Gravatar 的中国替代，中国线路与国际线路各一套节点。", "cravatar.com"),
        ("font", "Windfonts", "面向中文网页的字体服务，绑定本站后启用。", "windfonts.com"),
    ]
    if crossborder:
        brands.append(("store", "薇晓朵", "跨境店工具：微信支付 for WooCommerce、订单微信通知。", "weixiaoduo.com"))
    cols = "".join(
        f'<div class="eco-b"><div class="eco-bn">{ICON[i]}{n}</div><p>{d}</p><a href="#">{u}{ICON["external"]}</a></div>'
        for i, n, d, u in brands)
    return (f'<section class="eco{" cols-5" if crossborder else ""}">'
            f'<div class="eco-l"><div class="eyebrow">来自文派</div><h2>文派叶子是文派 WordPress 生态的入口</h2>'
            f'<p>上面这些核心服务由文派与合作方免费提供，叶子把它们接到你的站点里。</p><a class="btn btn-ghost" href="#">了解文派开源 {ICON["arrow"]}</a></div>'
            f'<div class="eco-r">{cols}</div></section>')
