#!/usr/bin/env python3
"""方向 E 页面生成器（v5 合并版）。运行：python3 E/build.py
所有面向用户的字符串都在本文件，即文案清单。"""
import pathlib, re, sys

ROOT = pathlib.Path(__file__).parent
sys.path.insert(0, str(ROOT))
from parts_v4 import pagehero as _ph, strip as _strip, sec, stats as _stats, resources as _res
from parts_v5 import hero_white as _hw, stat_area as _sa, routes_list, timeline, resources_compact as _rc

HEAD = (ROOT / "_shell-head.html").read_text(encoding="utf-8")
TAIL = "    </div>\n  </div>\n</body>\n</html>\n"

# ---------------------------------------------------------------- 图标：RemixIcon line（开源，Apache 2.0），按需 SVG，本地包，不走 CDN
# feibisi 2026-09-06 夜：Phosphor 不喜欢，换 RemixIcon。React 实现用 @remixicon/react 的同名组件（Ri + PascalCase，如 RiGlobalLine）。
RI_DIR = ROOT.parent / "node_modules" / "remixicon" / "icons"
RI = {  # 本文件用的语义名 → RemixIcon 文件名（实现侧 icons.js 用同一张表）
    "globe": "global-line", "bolt": "flashlight-line", "user": "user-3-line", "link": "link", "monitor": "computer-line",
    "check": "check-line", "info": "information-line", "arrow": "arrow-right-line", "map": "map-pin-line", "grid": "apps-line",
    "shield": "shield-check-line", "download": "download-2-line", "clock": "time-line", "block": "forbid-line", "gauge": "dashboard-3-line",
    "font": "font-size", "leaf": "leaf-line", "book": "book-open-line", "chat": "chat-3-line", "video": "video-line", "help": "question-line",
    "rss": "rss-line", "wechat": "wechat-line", "store": "store-2-line", "sparkle": "sparkling-line", "layers": "stack-line",
    "settings": "equalizer-line", "home": "home-4-line", "pulse": "pulse-line", "external": "external-link-line",
    "sliders": "eye-line", "image": "image-line", "chevron": "arrow-down-s-line", "up": "arrow-right-up-line", "spinner": "loader-4-line",
    "stethoscope": "stethoscope-line", "lifebuoy": "lifebuoy-line", "x": "close-line", "warn": "error-warning-line", "retry": "refresh-line",
    "plug": "plug-line", "server": "server-line", "cloud": "cloud-line", "bag": "shopping-bag-3-line", "key": "key-2-line",
    "card": "bank-card-line", "bell": "notification-3-line",
}
_RI_INDEX = {p.stem: p for p in RI_DIR.rglob("*.svg")}
def _ri_svg(name):
    raw = _RI_INDEX[RI[name]].read_text(encoding="utf-8")
    return raw.replace('<svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">', '<svg class="ph" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">', 1).strip()
ICON = {k: _ri_svg(k) for k in RI}

pagehero = lambda *a: _ph(ICON, *a)
strip = lambda items: _strip(ICON, items)
stats = lambda items: _stats(ICON, items)
resources = lambda cb=False: _res(ICON, cb)
hero_white = lambda *a, **kw: _hw(ICON, *a, **kw)
resources_compact = lambda cb=False: _rc(ICON, cb)
stat_area = lambda *a: _sa(ICON, *a)
from parts_v6 import hero_stack as _hs, routes_list as routes_list6, eco_block as _eco
hero_stack = lambda *a, **kw: _hs(ICON, *a, **kw)
eco_block = lambda cb=False: _eco(ICON, cb)
ENABLE_FONT = f'<a class="btn btn-ghost" href="services.html">启用</a>'

# ---------------------------------------------------------------- 壳
def shell(active, headright="", tabs=True, title="概览"):
    h = HEAD.replace("__HEADRIGHT__", headright).replace("<title>概览 · 文派叶子</title>", f"<title>{title} · 文派叶子</title>")
    for k in ("leaf", "help", "chat"):
        h = h.replace(f"__ICON_{k}__", ICON[k])
    for k in ("OVERVIEW", "SETTINGS", "SERVICES", "DIAGNOSE"):
        h = h.replace(f"__T_{k}__", "is-active" if k == active else "")
    for slug, ic, name in (("overview-domestic", "home", "概览"), ("settings", "settings", "设置"), ("services", "grid", "服务"), ("diagnose", "stethoscope", "诊断")):
        h = re.sub(r'(<a class="wpcy-tab[^"]*" href="%s.html">)%s</a>' % (slug, name), lambda m: m.group(1) + ICON[ic] + name + "</a>", h)
    if not tabs:
        h = re.sub(r'<nav class="wpcy-tabs".*?</nav>\n', "", h, flags=re.S)
    return h

def page(name, title, active, body, headright="", tabs=True):
    (ROOT / name).write_text(shell(active, headright, tabs, title) + body + TAIL, encoding="utf-8")

def foot(uptime="已运行 37 天"):
    return (f'<footer class="foot"><span>文派叶子 4.0.0 · {uptime}</span><span class="r">'
            '<a href="#">' + ICON["sparkle"] + '更新日志</a><a href="#">' + ICON["external"] + 'wpcy.com</a></span></footer>')
FOOT = foot()

def proto_switch(items, on):
    """原型专用：右下角固定的状态切换器，不属于产品 UI。items: (label, file)。"""
    a = "".join(f'<a class="{"on" if f == on else ""}" href="{f}">{t}</a>' for t, f in items)
    return f'<div class="proto-sw" aria-label="原型状态切换">原型<span class="seg">{a}</span></div>'

# ---------------------------------------------------------------- 部件
def card(icon, tone, title, sub, body, foot=None, pill=None):
    p = f'<span class="pill {pill[0]}">{pill[1]}</span>' if pill else ""
    f = f'<div class="card-foot">{foot}</div>' if foot else ""
    return f'<article class="card"><div class="card-head"><div class="tile {tone}">{ICON[icon]}</div><div><h2 class="card-title">{title}</h2><p class="card-sub">{sub}</p></div>{p}</div>{body}{f}</article>'

def head(title, sub, more=None):
    m = f'<a class="more" href="{more[1]}">{more[0]}</a>' if more else ""
    return f'<div class="card-head"><div><h2 class="card-title">{title}</h2><p class="card-sub">{sub}</p></div>{m}</div>'

def routes_table(rows):
    tr = "".join(f'<tr><td><div class="t">{a}</div><div class="d">{b}</div></td><td><span class="pill {c}">{d}</span></td><td class="num">{e}</td><td class="num">{f}</td></tr>' for a, b, c, d, e, f in rows)
    return f'<table class="tbl"><thead><tr><th>源</th><th>状态</th><th>延迟</th><th>最近检查</th></tr></thead><tbody>{tr}</tbody></table>'

def events(items):
    li = "".join(f'<li><time>{t}</time><div class="e"><span class="dot {c}"></span><div><div>{x}</div><div class="d">{d}</div></div></div></li>' for t, c, x, d in items)
    return f'<ul class="events">{li}</ul>'

def field(label, help_, ctl, hint=None, icon=None):
    h = f'<div class="hint">{hint}</div>' if hint else ""
    label = (ICON[icon] if icon else "") + label
    return f'<div class="field"><div><div class="field-label">{label}</div><div class="field-help">{help_}</div></div><div class="field-ctl">{ctl}{h}</div></div>'

def radios(name, items, on):
    return '<div class="opts">' + "".join(
        f'<label class="opt"><input type="radio" name="{name}" {"checked" if i == on else ""}><span><span class="t">{t}</span>{("<div class=d>" + d + "</div>") if d else ""}</span></label>'
        for i, (t, d) in enumerate(items)) + "</div>"

def mode_switch(adv):
    return f'<div class="mode">{ICON["sliders"]}显示 <span class="seg"><a class="{"" if adv else "on"}" href="settings.html">简单</a><a class="{"on" if adv else ""}" href="settings-advanced.html">高级</a></span></div>'

def simple_row(icon, tone, t, d, ctl):
    return f'<div class="simple-row"><div class="tile {tone}">{ICON[icon]}</div><div><div class="t">{t}</div><div class="d">{d}</div></div><div class="r">{ctl}</div></div>'

def stat_card(icon, k, v, unit, d, bars):
    b = "".join(f'<i style="height:{h}%" class="{"hi" if n == len(bars) - 1 else ""}"></i>' for n, h in enumerate(bars))
    return f'<article class="card stat"><div class="k">{ICON[icon]}{k}</div><div class="v">{v}<small>{unit}</small></div><div class="d">{d}</div><div class="bar" aria-hidden="true">{b}</div></article>'

NEXT_BIND = (f'<div class="next" style="margin-top:16px"><div class="tile">{ICON["link"]}</div><div><strong>下一步：绑定本站。</strong> '
             '绑定后可使用文派服务与小工具，数据保存在本站，随时可解除。</div><a class="btn btn-primary" href="services.html">绑定本站</a></div>')

# ---------------------------------------------------------------- 概览
# 规则：一页只有一个主按钮。有"下一步"条时 hero 里的按钮都用次级；健康环数值必须与线路列表一致。
OVERVIEW_STATES = [("国内站", "overview-domestic.html"), ("跨境站", "overview-crossborder.html"),
                   ("降级", "overview-degraded.html"), ("刚安装", "overview-empty.html"),
                   ("恢复模式", "overview-recovery.html"), ("升级站", "overview-upgraded.html")]

RECOVERY_BANNER = (f'<div class="notice warn act" style="margin-bottom:16px">{ICON["shield"]}<span><b>恢复模式已开启：</b>全部 URL 改写与模块已停用。站点现在的行为和未安装本插件时一样。</span>'
                   f'<a class="btn btn-secondary" href="#">退出恢复模式</a></div>')
PROFILE_PROMPT = (f'<div class="next" style="margin-top:16px"><div class="tile">{ICON["map"]}</div><div><strong>确认你的站点场景。</strong> '
                  '从 3.8 升级的站点先按「国内站」运行；如果服务器或访客在海外，选对场景后加速只作用于该作用的地方。</div><a class="btn btn-primary" href="settings.html">确认你的站点场景</a></div>')

def overview(state):
    domestic = state in ("domestic", "degraded", "empty", "recovery", "upgraded")
    CTA_DOM = f'<a class="btn btn-primary" href="diagnose.html">{ICON["pulse"]}运行诊断</a><a class="btn btn-secondary" href="settings.html">调整设置</a>'
    CTA_SEC = f'<a class="btn btn-secondary" href="diagnose.html">{ICON["pulse"]}查看线路详情</a><a class="btn btn-secondary" href="settings.html">调整设置</a>'
    STACK_DOM = [("on", "download", "WordPress 更新与安装包", "经 WenPai.org 镜像接通 · 原始源在国内常超时", "", None),
                 ("on", "bolt", "公共库", "经 adminCDN 接通 · 原始源在国内不可达", "", None),
                 ("on", "user", "头像", "经 Cravatar 接通 · Gravatar 在国内空白", "", None),
                 ("off", "font", "中文字体", "Windfonts 提供 · 绑定本站后可用", "", ENABLE_FONT)]
    S7_DOM = f'<div class="wpcy-grid-3">{stat_area("download","更新与安装包","42","次","12%","经 WenPai.org 镜像完成 · 节省下载约 1.2 GB",[18,26,22,31,28,24,42])}{stat_area("bolt","公共库请求","18,430","次","8%","经 adminCDN 接通 · 访客首屏更快",[2100,2400,2300,2700,2900,2600,3200])}{stat_area("user","头像请求","3,210","次","5%","经 Cravatar 接通 · 评论区不再空头像",[380,410,470,440,520,480,560])}</div>'
    if state in ("domestic", "upgraded"):
        hero = hero_stack("国内站 · 已运行 37 天", "原本在国内打不开的，<br>现在都能用了。",
                          "WordPress 更新、Google Fonts、Gravatar 头像这些在国内不可达或很慢的服务，已经由文派生态的 WenPai.org、adminCDN、Cravatar 接通。你和访客都不用再等海外线路。",
                          CTA_SEC if state == "upgraded" else CTA_DOM, STACK_DOM,
                          ("一切正常", ["3 项核心服务已接通", "1 项未启用", "最近检查 3 分钟前"]))
        nxt = PROFILE_PROMPT if state == "upgraded" else ""
        s7 = S7_DOM
        left = card_tight("线路状态", "每 10 分钟自动检查一次", ("查看全部", "diagnose.html"), routes_list6([
            ("ok", "WordPress.org 镜像", "WenPai.org", "更新检查与安装包", "229 ms", "3 分钟前"),
            ("ok", "公共库源", "adminCDN", "Google Fonts、Ajax、jsDelivr、Emoji", "61 ms", "3 分钟前"),
            ("ok", "CDNJS 源", "adminCDN", "昨天曾中断 18 分钟，已恢复", "74 ms", "3 分钟前"),
            ("ok", "头像", "Cravatar", "评论头像 · cravatar.cn", "48 ms", "3 分钟前")]))
        right = card_tight("最近动态", "线路切换与自动处理", ("查看全部", "diagnose.html"), timeline([
            ("ok", "今天 14:02", "完成 WordPress 7.1 更新检查", "经 WenPai.org 镜像，耗时 0.4 秒"),
            ("ok", "昨天 22:33", "CDNJS 源恢复，已切回", "adminCDN 中断 18 分钟，期间走原始上游，访客不受影响"),
            ("warn", "昨天 22:15", "CDNJS 源不可达，已回原始上游", "adminCDN 暂时不可达，恢复后自动切回"),
            ("", "9 月 4 日", "插件更新到 4.0.0", "从 3.8 迁移 12 项设置")]))
    elif state == "recovery":
        hero = hero_stack("国内站 · 已运行 37 天", "恢复模式已开启，<br>叶子现在什么都不做。",
                          "全部 URL 改写与模块已停用，站点回到未安装本插件时的行为。问题排除后退出恢复模式即可恢复之前的设置。",
                          f'<a class="btn btn-secondary" href="diagnose.html">{ICON["pulse"]}查看诊断</a><a class="btn btn-secondary" href="settings.html">查看设置</a>',
                          [("paused", "download", "WordPress 更新与安装包", "未接管 · 直连 WordPress.org", "", None),
                           ("paused", "bolt", "公共库", "未接管 · 直连原始源", "", None),
                           ("paused", "user", "头像", "未接管 · 直连 Gravatar", "", None),
                           ("paused", "font", "中文字体", "未启用", "", None)],
                          ("恢复模式", ["全部改写已停用", "设置已保留"]), tone="mute")
        nxt = ""
        s7 = S7_DOM
        left = card_tight("线路状态", "每 10 分钟自动检查一次 · 恢复模式下只检查不接管", ("查看全部", "diagnose.html"), routes_list6([
            ("ok", "WordPress.org 镜像", "WenPai.org", "更新检查与安装包", "229 ms", "3 分钟前"),
            ("ok", "公共库源", "adminCDN", "Google Fonts、Ajax、jsDelivr、Emoji", "61 ms", "3 分钟前"),
            ("ok", "CDNJS 源", "adminCDN", "备用公共库", "74 ms", "3 分钟前"),
            ("ok", "头像", "Cravatar", "评论头像 · cravatar.cn", "48 ms", "3 分钟前")]))
        right = card_tight("最近动态", "线路切换与自动处理", ("查看全部", "diagnose.html"), timeline([
            ("warn", "今天 15:10", "已进入恢复模式", "全部 URL 改写与模块已停用"),
            ("ok", "今天 14:02", "完成 WordPress 7.1 更新检查", "经 WenPai.org 镜像，耗时 0.4 秒"),
            ("", "9 月 4 日", "插件更新到 4.0.0", "从 3.8 迁移 12 项设置")]))
    elif state == "degraded":
        hero = hero_stack("国内站 · 已运行 37 天", "WenPai.org 镜像暂时不可达，<br>已自动回原始上游。",
                          "更新检查与安装包暂时直连 WordPress.org，会慢一些但不会失败；公共库与头像照常经 adminCDN、Cravatar 接通。恢复后自动切回，不需要你操作。",
                          CTA_SEC,
                          [("fallback", "download", "WordPress 更新与安装包", "WenPai.org 不可达 22 分钟 · 已回原始上游", "", None),
                           ("on", "bolt", "公共库", "经 adminCDN 接通 · 原始源在国内不可达", "", None),
                           ("on", "user", "头像", "经 Cravatar 接通 · Gravatar 在国内空白", "", None),
                           ("off", "font", "中文字体", "Windfonts 提供 · 绑定本站后可用", "", ENABLE_FONT)],
                          ("1 项已回退", ["WenPai.org 镜像不可达 22 分钟", "其余 2 项已接通", "访客不受影响"]), tone="warn")
        nxt = (f'<div class="notice warn act" style="margin-top:16px">{ICON["info"]}<span>镜像连续不可达超过 1 小时会提醒你；现在不需要做任何事。</span>'
               f'<a class="btn btn-secondary" href="#">立即重试</a></div>')
        s7 = S7_DOM
        left = card_tight("线路状态", "每 10 分钟自动检查一次 · 不可达时每 1 分钟重试", ("查看全部", "diagnose.html"), routes_list6([
            ("warn", "WordPress.org 镜像", "WenPai.org", "不可达，已回原始上游（api.wordpress.org · 2,410 ms）", "—", "1 分钟前"),
            ("ok", "公共库源", "adminCDN", "Google Fonts、Ajax、jsDelivr、Emoji", "61 ms", "3 分钟前"),
            ("ok", "CDNJS 源", "adminCDN", "备用公共库", "74 ms", "3 分钟前"),
            ("ok", "头像", "Cravatar", "评论头像 · cravatar.cn", "48 ms", "3 分钟前")]))
        right = card_tight("最近动态", "线路切换与自动处理", ("查看全部", "diagnose.html"), timeline([
            ("warn", "今天 14:20", "WordPress.org 镜像不可达，已回原始上游", "WenPai.org 镜像暂时不可达，每 1 分钟重试，恢复后自动切回"),
            ("ok", "今天 14:02", "完成 WordPress 7.1 更新检查", "经 WenPai.org 镜像，耗时 0.4 秒"),
            ("ok", "昨天 22:33", "CDNJS 源恢复，已切回", "adminCDN 中断 18 分钟，访客不受影响"),
            ("", "9 月 4 日", "插件更新到 4.0.0", "从 3.8 迁移 12 项设置")]))
    elif state == "empty":
        hero = hero_stack("国内站 · 刚安装", "已经开始为你接通，<br>数字稍后就有。",
                          "WordPress 更新、Google Fonts、Gravatar 头像已分别经 WenPai.org、adminCDN、Cravatar 接通。第一次自动线路检查已完成，计数从现在开始。",
                          CTA_DOM, STACK_DOM,
                          ("一切正常", ["3 项核心服务已接通", "刚安装，还没有统计"]))
        nxt = ""
        s7 = f'<div class="wpcy-grid-3">{stat_area("download","更新与安装包","还没有数据","","","下次更新检查后开始计数",[])}{stat_area("bolt","公共库请求","还没有数据","","","有访客打开页面后开始计数",[])}{stat_area("user","头像请求","还没有数据","","","有评论或用户头像加载后开始计数",[])}</div>'
        left = card_tight("线路状态", "每 10 分钟自动检查一次", ("查看全部", "diagnose.html"), routes_list6([
            ("ok", "WordPress.org 镜像", "WenPai.org", "更新检查与安装包", "231 ms", "刚刚"),
            ("ok", "公共库源", "adminCDN", "Google Fonts、Ajax、jsDelivr、Emoji", "63 ms", "刚刚"),
            ("ok", "CDNJS 源", "adminCDN", "备用公共库", "70 ms", "刚刚"),
            ("ok", "头像", "Cravatar", "评论头像 · cravatar.cn", "51 ms", "刚刚")]))
        right = card_tight("最近动态", "线路切换与自动处理", ("查看全部", "diagnose.html"), timeline([
            ("ok", "刚刚", "首次线路检查完成", "4 条线路全部正常"),
            ("", "刚刚", "已按「国内站」配置", "更新走 WenPai.org 镜像，公共库与头像经 adminCDN、Cravatar 接通")]))
    else:
        hero = hero_stack("跨境 · 外贸站 · 已运行 37 天", "人在国内、站在海外，<br>后台不该卡。",
                          "只对你在后台看到的资源做接通——Google Fonts、Gravatar 这些在国内打不开的，后台里由 adminCDN、Cravatar 接通；海外访客看到的前台一个字节不动；更新直连 WordPress.org。",
                          f'<a class="btn btn-secondary" href="diagnose.html">{ICON["gauge"]}从我的浏览器测速</a><a class="btn btn-secondary" href="settings.html">调整设置</a>',
                          [("direct", "download", "WordPress 更新与安装包", "直连 WordPress.org · 服务器在海外不需镜像", "", None),
                           ("on", "bolt", "后台公共库", "后台里经 adminCDN 接通 · 原始源在国内打不开", "只在后台", None),
                           ("on", "user", "后台头像", "后台里经 Cravatar 接通 · Gravatar 在国内空白", "只在后台", None),
                           ("off", "font", "中文字体", "Windfonts 提供 · 绑定本站后可用", "", ENABLE_FONT)],
                          ("一切正常", ["2 项核心服务已接通", "更新直连", "未绑定"]))
        nxt = NEXT_BIND
        s7 = f'<div class="wpcy-grid-3">{stat_area("bolt","后台公共库请求","6,120","次","9%","经 adminCDN 接通 · 只在后台生效",[640,700,760,720,820,880,920])}{stat_area("clock","后台心跳请求","2,340","次","","已节省 · 仪表盘关闭、编辑器 60 秒一次",[300,310,290,330,350,340,380])}{stat_area("block","出站请求","418","次","","已屏蔽 · WordPress 新闻、活动等仪表盘外部内容",[50,58,52,61,66,60,70])}</div>'
        left = card_tight("从你的浏览器测速", "上次 2 小时前 · 原始源与接通后对照", ("重新测速", "diagnose.html"), routes_list6([
            ("warn", "你的服务器", "站点", "后台 TTFB", "1,840 ms", "2 小时前"),
            ("bad", "fonts.googleapis.com", "原始源", "后台已改写", "超时", "2 小时前"),
            ("ok", "googlefonts.admincdn.com", "adminCDN", "接通后", "210 ms", "2 小时前"),
            ("bad", "gravatar.com", "原始源", "后台已改写", "超时", "2 小时前"),
            ("ok", "cn.cravatar.com", "Cravatar", "接通后", "96 ms", "2 小时前")]))
        right = card_tight("最近动态", "线路切换与自动处理", ("查看全部", "diagnose.html"), timeline([
            ("ok", "今天 14:02", "后台 Google Fonts 已经 adminCDN 接通", "本次会话 38 个请求"),
            ("", "今天 09:30", "完成 WordPress 7.1 更新检查", "直连 WordPress.org，耗时 1.1 秒"),
            ("warn", "昨天 18:40", "浏览器测速：gravatar.com 超时", "后台头像已经 Cravatar 接通，不受影响"),
            ("", "9 月 4 日", "插件更新到 4.0.0", "从 3.8 迁移 12 项设置；后台加速设置已保留，4.1 起生效")]))
    cur = f"overview-{state}.html"
    return f"""
      <main class="wpcy-main wpcy-wrap">
        {RECOVERY_BANNER if state == "recovery" else ""}
        {hero}
        {nxt}
        <section class="sec"><div class="sec-h caps"><h2>过去 7 天为你处理</h2><p>数字来自本站计数</p></div>{s7}</section>
        <section class="sec"><div class="grid2-w">{left}{right}</div></section>
        {eco_block(not domestic)}
        {foot("刚安装") if state == "empty" else FOOT}
      </main>
      {proto_switch(OVERVIEW_STATES, cur)}
"""

def card_tight(title, sub, more, body):
    return f'<article class="card tight">{head(title, sub, more)}{body}</article>'

page("overview-domestic.html", "概览", "OVERVIEW", overview("domestic"))
page("overview-crossborder.html", "概览", "OVERVIEW", overview("crossborder"))
page("overview-degraded.html", "概览", "OVERVIEW", overview("degraded"))
page("overview-empty.html", "概览", "OVERVIEW", overview("empty"))
page("overview-recovery.html", "概览", "OVERVIEW", overview("recovery"))
page("overview-upgraded.html", "概览", "OVERVIEW", overview("upgraded"))

# ---------------------------------------------------------------- 设置
SCENE_CARD = f"""
          <section class="card">
            <h2 class="section-title">{ICON["map"]} 站点场景</h2>
            <p class="section-desc">场景决定下面各项的默认组合；每一项之后仍可单独调整。</p>
            <div class="choice-cards">
              <label class="choice"><input type="radio" name="p"><span><div class="t">国内站</div><div class="d">服务器、访客、管理员都在中国大陆</div></span></label>
              <label class="choice is-on"><input type="radio" name="p" checked><span><div class="t">跨境 · 外贸站</div><div class="d">服务器与访客在海外，管理员在中国大陆</div></span></label>
              <label class="choice"><input type="radio" name="p"><span><div class="t">混合站</div><div class="d">服务器在海外，访客中外都有，管理员在中国大陆</div></span></label>
            </div>
            <div class="notice info" style="margin-top:12px">{ICON["info"]}<span>切换场景会把下面各项重置为该场景的默认组合。根据服务器位置和你的浏览器，我们建议：跨境 · 外贸站。</span></div>
            <p class="hint" style="margin-top:12px;color:var(--wpcy-ink-3);font-size:12px">拿不准？<a href="onboarding.html">重新运行首次设置向导</a></p>
          </section>"""

settings_simple = f"""
      <main class="wpcy-main wpcy-wrap">
        <div class="wpcy-page-head"><div><h1 class="wpcy-h1">设置</h1><p class="wpcy-lede">改动即时生效，不需要保存</p></div>{mode_switch(False)}</div>
        <div class="wpcy-stack">
          {SCENE_CARD}
          <section class="card">
            <h2 class="section-title">{ICON["bolt"]} 加速与优化</h2>
            <p class="section-desc">按当前场景已配好，通常不需要改。想看具体走哪个源、只在后台还是前台生效，切到右上角的「高级」。</p>
            {simple_row("download", "ok", "WordPress 更新与安装", "按场景自动选择：跨境站直连 WordPress.org，国内站走 WenPai.org 镜像", '<span class="pill ok">直连 WordPress.org</span>')}
            {simple_row("bolt", "ok", "公共库接通", "Google Fonts、Ajax、jsDelivr 等改到 adminCDN 的国内节点", '<span class="scope">只在后台</span><span class="toggle on"><i></i>已开启</span>')}
            {simple_row("user", "ok", "头像接通", "Gravatar 在国内空白，改由 Cravatar 提供", '<span class="scope">只在后台</span><span class="toggle on"><i></i>已开启</span>')}
            {simple_row("font", "", "中文字体", "由 Windfonts 提供，让站点用上更好看的中文字体", '<a class="scope" href="services.html">绑定本站后可用</a><span class="toggle off-dis"><i></i>未开启</span>')}
          </section>
          <section class="card">
            <h2 class="section-title">{ICON["monitor"]} 后台体验</h2>
            <p class="section-desc">为人在中国大陆、操作海外后台的管理员减少等待。</p>
            {simple_row("clock", "ok", "减少后台心跳", "仪表盘不再轮询，编辑器 60 秒一次", '<span class="toggle on"><i></i>已开启</span>')}
            {simple_row("block", "ok", "不加载仪表盘的外部内容", "WordPress 新闻、活动等出站请求", '<span class="toggle on"><i></i>已开启</span>')}
          </section>
        </div>
        {FOOT}
      </main>
"""
page("settings.html", "设置", "SETTINGS", settings_simple)

settings_adv = f"""
      <main class="wpcy-main wpcy-wrap">
        <div class="wpcy-page-head"><div><h1 class="wpcy-h1">设置</h1><p class="wpcy-lede">改动即时生效，不需要保存 · 高级模式显示每项的源与作用域</p></div>{mode_switch(True)}</div>
        <div class="wpcy-stack">
          {SCENE_CARD}
          <section class="card">
            <h2 class="section-title">{ICON["globe"]} 连通性</h2>
            <p class="section-desc">每一项都可以选择只作用于后台、只作用于前台，或两者。</p>
            {field("WordPress.org 源", "更新检查与安装包从哪里取 · 镜像由 WenPai.org 提供", radios("org", [("国内镜像", "优先国内镜像，不可用时回原始上游 · 国内站默认"), ("直连 WordPress.org", "不经过镜像 · 跨境站默认")], 1) + '<span class="saved">已保存 ✓</span>', icon="download")}
            {field("公共前端库", "把常用 CDN 改到 adminCDN 的国内节点", '<div class="seg"><span>后台与前台</span><span class="on">仅后台</span><span>仅前台</span><span>关闭</span></div><div class="chk" style="margin-top:12px"><label><input type="checkbox" checked>Google Fonts</label><label><input type="checkbox" checked>Google Ajax</label><label><input type="checkbox">CDNJS</label><label><input type="checkbox" checked>jsDelivr</label><label><input type="checkbox" checked>Emoji</label></div>', icon="bolt")}
            {field("后台头像", "管理员在后台看到的头像源 · 由 Cravatar 提供", radios("ah", [("Cravatar 中国线路", "cravatar.cn · 国内节点"), ("Cravatar 国际线路", "cravatar.com · 全球节点"), ("关闭", "保留 Gravatar")], 0), icon="user")}
            {field("前台头像", "访客在评论等处看到的头像源 · 由 Cravatar 提供", radios("fh", [("Cravatar 中国线路", "cravatar.cn · 国内节点"), ("Cravatar 国际线路", "cravatar.com · 全球节点"), ("关闭", "保留 Gravatar")], 2), "跨境站默认关闭：海外访客直连 Gravatar 更快", icon="user")}
            {field("字体", "中文字体替换 · 由 Windfonts 提供", '<span class="toggle off-dis"><i></i>启用 Windfonts</span>', "绑定本站后可用", icon="font")}
            {field("后台加速", "压缩与合并后台静态资源", '<span class="toggle off-dis"><i></i>启用后台加速</span> <span class="pill">即将提供</span>', "3.x 的设置已保留，4.1 起生效", icon="layers")}
          </section>
          <section class="card">
            <h2 class="section-title">{ICON["monitor"]} 后台体验</h2>
            <p class="section-desc">为人在中国大陆、操作海外后台的管理员减少等待。</p>
            {field("减少后台心跳", "仪表盘不再轮询，编辑器 60 秒一次", '<span class="toggle on"><i></i>已开启</span>', icon="clock")}
            {field("不加载仪表盘的外部内容", "WordPress 新闻、活动等出站请求", '<span class="toggle on"><i></i>已开启</span>', icon="block")}
          </section>
        </div>
        {FOOT}
      </main>
"""
page("settings-advanced.html", "设置", "SETTINGS", settings_adv)

# 多站点子站管理员：网络策略只读。控件全部禁用，页首一条说明，每张卡右上角"已由网络设定"。
settings_network = (settings_simple
    .replace('<p class="wpcy-lede">改动即时生效，不需要保存</p>', '<p class="wpcy-lede">已由网络设定 · 本站不能单独修改</p>')
    .replace(f'{mode_switch(False)}', '')
    .replace('<div class="wpcy-stack">', f'<div class="wpcy-stack"><div class="notice info">{ICON["info"]}<span>这些设置由网络管理员统一设定。需要本站单独调整时，请联系网络管理员在网络后台开放「允许子站覆盖」。</span></div>', 1)
    .replace('<h2 class="section-title">', '<h2 class="section-title"><span class="pill" style="margin-left:auto;order:2">已由网络设定</span>')
    .replace('class="toggle on"', 'class="toggle on off-dis"')
    .replace('<label class="choice is-on"><input type="radio" name="p" checked>', '<label class="choice is-on" style="opacity:.6"><input type="radio" name="p" checked disabled>')
    .replace('<label class="choice"><input type="radio" name="p">', '<label class="choice" style="opacity:.6"><input type="radio" name="p" disabled>')
    .replace('<p class="hint" style="margin-top:12px;color:var(--wpcy-ink-3);font-size:12px">拿不准？<a href="onboarding.html">重新运行首次设置向导</a></p>', '')
    .replace('场景决定下面各项的默认组合；每一项之后仍可单独调整。', '网络管理员为全网络选定的场景。')
    .replace(f'<div class="notice info" style="margin-top:12px">{ICON["info"]}<span>切换场景会把下面各项重置为该场景的默认组合。根据服务器位置和你的浏览器，我们建议：跨境 · 外贸站。</span></div>', '')
    .replace('按当前场景已配好，通常不需要改。想看具体走哪个源、只在后台还是前台生效，切到右上角的「高级」。', '按网络选定的场景配好。'))
page("settings-network.html", "设置", "SETTINGS", settings_network)

# ---------------------------------------------------------------- 服务
SERVICES_STATES = [("未绑定", "services.html"), ("绑定中", "services-pending.html"), ("已绑定", "services-bound.html"), ("服务端不可达", "services-unreachable.html")]

def providers_block(state):
    """供应商（决定 D4）：文派集市（即将开放）+ 薇晓朵商城（可连接）。state: locked / unconnected / connected"""
    if state == "locked":
        wxd = ('<div class="prov-card"><div class="prov-h">' + ICON["store"] + '<b>薇晓朵商城</b><span class="pill">未连接</span></div>'
               '<p>薇晓朵的服务与产品：微信支付 for WooCommerce、订单微信通知、跨境店运维。已购的产品连接后自动接收更新。</p>'
               '<div class="prov-f"><span class="scope">绑定本站后可连接</span></div></div>')
    elif state == "unconnected":
        wxd = ('<div class="prov-card"><div class="prov-h">' + ICON["store"] + '<b>薇晓朵商城</b><span class="pill">未连接</span></div>'
               '<p>薇晓朵的服务与产品：微信支付 for WooCommerce、订单微信通知、跨境店运维。已购的产品连接后自动接收更新。</p>'
               '<div class="prov-f"><span class="meta" style="margin:0">用购买时的邮箱和授权密钥连接</span><a class="btn btn-secondary" href="#">连接</a></div></div>')
    else:
        wxd = ('<div class="prov-card is-on"><div class="prov-h">' + ICON["store"] + '<b>薇晓朵商城</b><span class="pill ok">已连接</span></div>'
               '<p>账户 <span class="mono">l***@example.com</span> · 连接于 2026-09-03 · 已购 2 项产品在"可用服务"里</p>'
               '<div class="prov-f"><span class="meta" style="margin:0">授权密钥只保存在本站，已加密</span><a class="btn btn-ghost" href="#">断开</a></div></div>')
    wp = ('<div class="prov-card is-soon"><div class="prov-h">' + ICON["bag"] + '<b>文派集市</b><span class="pill">即将开放</span></div>'
          '<p>文派官方商城：文派系插件与主题的商业版本、模板与服务。开放后在这里连接。</p>'
          '<div class="prov-f"><a class="btn btn-ghost" href="#">了解文派集市 ' + ICON["arrow"] + '</a></div></div>')
    return f'<section class="sec">{sec("供应商", "连接后，你在该供应商购买的服务与产品会出现在下面并自动接收更新")}<div class="prov-grid">{wxd}{wp}</div></section>'

def services_body(state):
    bound = state in ("bound", "unreachable")
    sw = proto_switch(SERVICES_STATES, "services.html" if state == "unbound" else f"services-{state}.html")
    empty_apps = f'<section class="sec">{sec("小工具")}<article class="card"><div class="empty"><div class="tile">{ICON["grid"]}</div><p>绑定本站后，这里会出现可用的小工具。</p><p class="meta">小工具在沙箱中运行，只能访问它申请过的权限</p></div></article></section>'
    if state == "unbound":
        main = f"""
        {card("link", "accent", "尚未绑定本站", "绑定是匿名的：服务端只记录站点标识，不需要注册账号",
              '<p class="big">绑定后可使用文派服务与小工具</p><p class="meta">数据保存在本站 · 随时可解除 · 不影响任何加速功能</p>',
              '<a class="btn btn-ghost" href="#">了解文派服务 ' + ICON["arrow"] + '</a><a class="btn btn-primary" href="services-pending.html">绑定本站</a>', ("", "未绑定"))}
        {providers_block("locked")}
        {empty_apps}
        """
    elif state == "pending":
        main = f"""
        {card("link", "accent", "正在绑定本站", "等待文派服务器验证，通常几秒内完成",
              '<p class="big"><span class="spin"></span>等待验证</p><p class="meta">如果超过一分钟没有完成，可以取消后重试；不影响任何加速功能</p>',
              '<a class="btn btn-secondary" href="services.html">取消</a>', ("", "绑定中"))}
        {providers_block("locked")}
        {empty_apps}
        """
    else:
        rows = [("Windfonts 中文字体", "Windfonts", "前台中文字体替换", "ok", "已启用", '<a class="btn btn-ghost" href="settings.html">设置</a>'),
                ("微信支付 for WooCommerce", "薇晓朵", "让中国买家在你的海外店用微信付款 · 已购，更新经供应商接收", "ok", "已安装", '<a class="btn btn-ghost" href="#">管理</a>'),
                ("订单微信通知", "薇晓朵", "新订单、退款实时推送到微信 · 已购，尚未安装", "", "未安装", '<a class="btn btn-primary" href="#">安装</a>'),
                ("跨境店运维", "薇晓朵", "海外服务器与线路的托管运维服务", "", "未购买", '<a class="btn btn-ghost" href="#">了解 ' + ICON["arrow"] + '</a>')]
        rr = "".join(f'<div><div><div class="t">{a}<span class="prov">{pv}</span></div><div class="d">{b}</div></div><span class="pill {c}">{d}</span><span class="r">{e}</span></div>' for a, pv, b, c, d, e in rows)
        apps = "".join(f'<a class="app" href="#"><div class="tile accent">{ICON[i]}</div><div class="t">{n}</div><div class="d">{d}</div></a>' for i, n, d in
                       [("bolt", "连接测速", "从浏览器测各源耗时"), ("font", "字体预览", "看 Windfonts 在本站的效果"), ("clock", "通知设置", "订单通知的时间与渠道"), ("help", "帮助中心", "文档与反馈")])
        unreachable = (f'<div class="notice warn act" style="margin-bottom:16px">{ICON["info"]}<span>暂时无法连接文派服务，显示的是 3 小时前的状态。加速功能不受影响。</span><a class="btn btn-secondary" href="#">重试</a></div>'
                       if state == "unreachable" else "")
        apps_block = (f'<section class="sec">{sec("小工具", "在沙箱中运行，只能访问它申请过的权限")}<div class="apps">{apps}</div></section>' if state == "bound" else
                      f'<section class="sec">{sec("小工具")}<article class="card"><div class="empty"><div class="tile">{ICON["grid"]}</div><p>小工具目录暂时不可用。</p><p class="meta">连接恢复后会自动显示；已打开过的小工具数据仍保存在本站</p></div></article></section>')
        main = f"""
        {unreachable}
        <article class="card"><div class="card-head"><div class="tile ok">{ICON["link"]}</div><div><h2 class="card-title">本站已绑定</h2><p class="card-sub">站点标识 <span class="mono">a3f9…c21e</span> · 绑定于 2026-09-02</p></div><span class="pill ok">已绑定</span></div>
          <div class="card-foot start"><span class="meta" style="margin:0">数据保存在本站；解除绑定后小工具数据保留 30 天</span><a class="btn btn-secondary" href="services.html">解除绑定</a></div></article>
        {providers_block("connected")}
        <section class="sec">{sec("可用服务", "来自已连接供应商与文派服务，按你的站点场景与已安装插件显示")}<article class="card"><div class="rows svc">{rr}</div></article></section>
        {apps_block}
        """
    return f"""
      <main class="wpcy-main wpcy-wrap">
        <div class="wpcy-page-head"><div><h1 class="wpcy-h1">服务</h1><p class="wpcy-lede">文派服务与小工具，按站点场景显示</p></div></div>
        {main}
        {FOOT}
      </main>
      {sw}
"""
page("services.html", "服务", "SERVICES", services_body("unbound"))
page("services-pending.html", "服务", "SERVICES", services_body("pending"))
page("services-bound.html", "服务", "SERVICES", services_body("bound"))
page("services-unreachable.html", "服务", "SERVICES", services_body("unreachable"))

# ---------------------------------------------------------------- 诊断
diag = f"""
      <main class="wpcy-main wpcy-wrap">
        <div class="wpcy-page-head"><div><h1 class="wpcy-h1">诊断</h1><p class="wpcy-lede">线路检查、浏览器测速、迁移记录与恢复</p></div><a class="btn btn-secondary" href="#">{ICON["download"]} 导出诊断报告</a></div>
        <section class="sec">{sec("从你的服务器到各源", "每 10 分钟自动检查 · 上次 3 分钟前", ("立即检查", "#"))}
          <article class="card tight">{routes_table([("WordPress.org 镜像", "WenPai.org · 更新检查与安装包", "ok", "正常", "229 ms", "3 分钟前"), ("WordPress.org 直连", "api.wordpress.org · 镜像不可达时的回退", "warn", "偏慢", "2,410 ms", "3 分钟前"), ("公共库源", "adminCDN · Google Fonts、Ajax、jsDelivr、Emoji", "ok", "正常", "61 ms", "3 分钟前"), ("头像", "Cravatar · cravatar.cn", "ok", "正常", "48 ms", "3 分钟前"), ("文派服务", "绑定与小工具", "ok", "正常", "112 ms", "3 分钟前")])}</article>
        </section>
        <section class="sec">{sec("从你的浏览器到各源", "用你现在的网络测，区分是插件层还是网络层的问题 · 上次 2 小时前", ("重新测速", "#"))}
          <article class="card tight">
            {routes_table([("你的服务器", "站点后台 TTFB", "warn", "偏慢", "1,840 ms", "2 小时前"), ("fonts.googleapis.com", "原始源", "bad", "超时", "—", "2 小时前"), ("googlefonts.admincdn.com", "adminCDN · 接通后", "ok", "正常", "210 ms", "2 小时前"), ("gravatar.com", "原始源", "bad", "超时", "—", "2 小时前"), ("cn.cravatar.com", "Cravatar · 接通后", "ok", "正常", "96 ms", "2 小时前")])}
            <div class="notice info" style="margin:12px 0 8px">{ICON["info"]}<span>后台慢主要来自你到服务器的往返（1,840 ms），插件层能改写的资源已改写；换线路或就近节点才能进一步改善。</span></div>
          </article>
        </section>
        <section class="sec">{sec("记录")}<div class="wpcy-grid-2">
          <article class="card tight">{head("迁移记录", "从 3.x 升级时的设置迁移", ("查看详情", "#"))}
            <div class="rows"><div><div><div class="t">2026-09-04 从 3.8 升级到 4.0.0</div><div class="d">12 项已迁移 · 3 项已不再需要 · 后台加速设置已保留（4.1 起生效）</div></div><span class="pill ok r">成功</span></div>
            <div><div><div class="t">备份</div><div class="d">3.8 的原始设置已完整保留，装回 3.8 时自动使用</div></div><a class="btn btn-ghost r" href="#">导出</a></div></div>
          </article>
          <article class="card tight">{head("出站主机记录", "插件按主机表处理过的出站请求（不含内容）", ("查看全部", "#"))}
            <table class="tbl"><thead><tr><th>主机</th><th>类别</th><th>次数</th><th>最近</th></tr></thead><tbody>
            <tr><td class="t">api.wordpress.org</td><td class="d">更新检查</td><td class="num">1,204</td><td class="num">3 分钟前</td></tr>
            <tr><td class="t">planet.wordpress.org</td><td class="d">仪表盘新闻</td><td class="num">418</td><td class="num">今天</td></tr>
            <tr><td class="t">stats.wp.com</td><td class="d">统计</td><td class="num">96</td><td class="num">昨天</td></tr></tbody></table>
          </article>
        </div></section>
        <section class="sec">{sec("数据与恢复")}<article class="card"><div class="rows">
          <div><div><div class="t">进入恢复模式</div><div class="d">一键停用所有 URL 改写与模块；恢复页不依赖 JavaScript，后台样式错乱时也能打开</div></div><a class="btn btn-secondary r" href="recovery.html">进入恢复模式</a></div>
          <div><div><div class="t">小工具数据</div><div class="d">按小工具导出或删除它保存在本站的数据</div></div><a class="btn btn-ghost r" href="#">管理</a></div>
        </div></article></section>
        {FOOT}
      </main>
"""
page("diagnose.html", "诊断", "DIAGNOSE", diag)

# ---------------------------------------------------------------- 向导
def wiz(step, title, lede, inner, actions):
    names = ["站点场景", "已为你做的", "绑定本站", "完成"]
    st = "".join(f'<div class="st {"cur" if n == step else ("done" if n < step else "")}"><span class="n">{("✓" if n < step else n + 1)}</span>{nm}</div>' + ('<div class="bar"></div>' if n < 3 else "") for n, nm in enumerate(names))
    return f"""
      <main class="wizard">
        <div class="steps">{st}</div>
        <h1>{title}</h1>
        <p class="lede">{lede}</p>
        {inner}
        <div class="actions">{actions}</div>
      </main>
"""

choice_cards = """<div class="choice-cards">
          <label class="choice"><input type="radio" name="p"><span><div class="t">国内站</div><div class="d">服务器、访客、管理员都在中国大陆</div></span></label>
          <label class="choice is-on"><input type="radio" name="p" checked><span><div class="t">跨境 · 外贸站</div><div class="d">服务器与访客在海外，管理员在中国大陆</div></span></label>
          <label class="choice"><input type="radio" name="p"><span><div class="t">混合站</div><div class="d">服务器在海外，访客中外都有，管理员在中国大陆</div></span></label>
        </div>"""
page("onboarding.html", "首次设置", "", wiz(0, "你的站点在哪里？", "我们据此决定哪些加速只作用于后台、哪些作用于访客。以后都可以在设置里改。",
     choice_cards + f'<div class="notice info" style="margin-top:12px">{ICON["info"]}<span>根据服务器位置和你的浏览器，我们建议：跨境 · 外贸站。</span></div>',
     '<a class="btn btn-primary" href="onboarding-2.html">继续</a><a class="btn btn-ghost" href="overview-crossborder.html">跳过，稍后设置</a>'), tabs=False)

done_rows = "".join(f'<div><div class="tile ok" style="width:28px;height:28px;border-radius:8px">{ICON["check"]}</div><div><div class="t">{a}</div><div class="d">{b}</div></div></div>' for a, b in [
    ("WordPress.org 直连", "你的服务器在海外，直连更快；国内镜像已关闭"),
    ("后台公共库走国内可达源", "Google Fonts、Ajax、jsDelivr 只在后台改写；海外访客看到的前台不变"),
    ("后台头像走 Cravatar", "前台保留 Gravatar"),
    ("后台心跳已节流，仪表盘外部内容已屏蔽", "减少你在国内操作海外后台的等待")])
page("onboarding-2.html", "首次设置", "", wiz(1, "已按「跨境 · 外贸站」配置好", "这些都是默认组合，以后可以在设置里逐项改。",
     f'<article class="card"><div class="rows">{done_rows}</div></article>',
     '<a class="btn btn-primary" href="onboarding-3.html">继续</a><a class="btn btn-ghost" href="onboarding.html">返回</a>'), tabs=False)
page("onboarding-3.html", "首次设置", "", wiz(2, "要绑定本站吗？", "绑定后可使用文派服务与小工具。绑定是匿名的，不需要注册账号；数据保存在本站，随时可解除。",
     f'<article class="card"><div class="rows"><div><div class="tile accent" style="width:28px;height:28px;border-radius:8px">{ICON["grid"]}</div><div><div class="t">小工具</div><div class="d">连接测速、字体预览、通知设置等，在沙箱中运行</div></div></div><div><div class="tile accent" style="width:28px;height:28px;border-radius:8px">{ICON["link"]}</div><div><div class="t">文派服务</div><div class="d">Windfonts 中文字体、面向跨境店的支付与通知服务</div></div></div></div></article>',
     '<a class="btn btn-primary" href="onboarding-4.html">绑定本站</a><a class="btn btn-ghost" href="onboarding-4.html">暂不，稍后再说</a>'), tabs=False)
page("onboarding-4.html", "首次设置", "", wiz(3, "一切就绪", "文派叶子已在后台运行。你随时可以回到概览查看它为你处理了什么。",
     f'<div class="wpcy-grid-3">{stat_area("globe", "更新", "直连", "", "", "WordPress.org", [])}{stat_area("bolt", "后台资源", "4 项", "", "", "已走国内可达源", [])}{stat_area("link", "服务", "已绑定", "", "", "站点标识 a3f9…c21e", [])}</div>'.replace('class="v none"', 'class="v"'),
     '<a class="btn btn-primary" href="overview-crossborder.html">进入概览</a>'), tabs=False)

# ---------------------------------------------------------------- 恢复
recovery = f"""
      <main class="wizard" style="max-width:560px">
        <article class="card"><div class="card-head"><div class="tile warn">{ICON["shield"]}</div><div><h2 class="card-title">文派叶子 · 恢复模式</h2><p class="card-sub">此页不依赖 JavaScript，后台样式错乱或站点无法访问时也能打开</p></div></div>
          <p style="margin:0 0 4px">先试第一项；不够再用第二项。两项都不会删除你的设置，随时可在设置里重新开启。</p>
          <div class="rows recover">
            <div><div><div class="t">只关闭 URL 改写</div><div class="d">页面里的资源地址回到原始来源；心跳节流、仪表盘屏蔽等其它功能保留。后台样式错乱时通常这一步就够。</div></div><a class="btn btn-secondary r" href="#">关闭 URL 改写</a></div>
            <div><div><div class="t">停用全部模块</div><div class="d">相当于停用本插件，但保留全部设置与迁移记录。站点仍无法访问时用这一步。</div></div><a class="btn btn-danger r" href="#">停用全部模块</a></div>
          </div>
          <div class="card-foot" style="justify-content:flex-start"><a class="btn btn-ghost" href="overview-domestic.html">返回概览</a></div>
        </article>
      </main>
"""
page("recovery.html", "恢复模式", "", recovery, tabs=False)
print("E built: 19 pages")
