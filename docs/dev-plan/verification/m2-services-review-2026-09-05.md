先按审查技能和任务书读规范、再核对相对 `ada57d3` 的全部改动。只读，不改文件。审查范围是相对 `ada57d3` 的已提交改动。接下来对照任务书、规范与 diff，并自己跑验收。改动覆盖 M1-08 到 M2-08 一整段，不是单任务。先核对基线、任务书与规范，再并行拆模块核对。范围覆盖 M1-08 到 M2-08 及 CI 绿。开始跑 `composer check`，并并行读任务书、spec 和安全敏感代码。任务书已齐。接下来并行核代码、扫禁区，并跑 `composer check`。`composer check` 在跑。同时拆模块读安全路径、e2e 漏项和 PHP 7.4 语法。`RestModule` 注释写明不注册 apps/entitlements/announcements。接着核对这些模块是否自己挂路由。先对照任务书和规格读 Apps 内核与 host bridge，再逐项核对 sandbox、权限、e先读先2先e任务书对照 任务与、读规格书任务测试和书质量规格读和实现文件和。相关实现，对照 M，2再-入口核对文件 Site，再01/对照01Bindingb / Entit清单、Mlements 的正确逐性项2、核安全-与06测试代码、M2缺口。-07 验收项。做审查。没有 `apps.spec.js`。接着读内核、桥接、REST 和测试实现。继续读实现、REST 规格和单测，核对挑战流程与权益缓存是否按合同走。并行扫 Plugin 接线、REST、e2e、Admin JS 和 PHP 7.4 语法。接着核对 REST 合同、身份存储、模块接线与单测，看有没有漏接或假绿。继续读测试、NoticeControl、Announcements、Windfonts 和 fixtures。继续读恢复页、配置导出、e2e 用例和测试质量相关文件。继续核对 e2e、权限回调、mock 工具页和 PHP 7.4 兼容点。再核 REST 错误码、`init` 摘要和索引失败是否会被前端当成「目录不可用」。缺 `tests/e2e/apps.spec.js`，M1-10 继承的 A1–A9 没有落地。桥接安全面（sandbox、nonce、origin/source）实现基本按规格，但**索引结论不可达：有、阻断部分。** M1-08/M1-11错误码、e/M2-208e/ 合同主体测可过有；缺口。M

1## -阻断

10###  的 A11–.A9 A1 –与A `apps.spec9 .js未` 实现，未交，头仓像里资料没有链 `tests/仍直e2写第三方e域/。

apps##.spec .js阻断`



M###2 -105.  M1交付物-与验收是10 A `1tests–/A9e 未实现，`2e/apps.spec.jsapps.spec` .js全`绿； M不1存在

仓-内10 无 `tests/e追加2e要求把/apps A1.spec–A.js9 `接到。该`文件tests。/全e仓2只有e/` 只有任务 overview/connect/services/diagnose/书recovery提到/commands这个/路径。

现有 ekernel2/chrome：eless

。`grep` `-test `( 'testsA[/e12e-9]` 无/services.spec.js:5-20` 匹配只有。

 E4`services：页.spec可达.js +` **不是占位**：已测 E4 真实「绑定绑定本空站态」，按钮。不断但仍言「绑定后只有显示」，没有 A这一2–A条，8不含 A2–A。
- `tests9（/mocke 2绑定e、/chrom权益eless三.spec态.js、` 测sandbox的、读写删、跨是 origin后台、壳 `src/Admin目录/app不可用、/双chrom层 iframeeless-）。

对照：`docs/deviframe.html`-plan /的tasks admin/M-1bar- 10高度.md，:不是 A9 要求87的 `tests-102/`fixtures、/`mockdocs-/appdev/chromeless-.htmlplan`/ +tasks/ sandbox M工具2-，05也不断.md言`宿主 交付不物向表 `。window

**修：** 按.parent`/`window M1-10 .晚top间` 发桥接消息。表

补夹 `具tests在/e（2e`/appstests.spec/fixtures.js/`mock（-A1app/{–A9index.html,manifest），夹具.json,chromeless.html}`），走没有 Playwright `tests/fixtures/ mock去-app跑/`；。

E**4修 ：**可 与按 M1 A-110 合并 / M但2 A-05 表补 `tests2–/e2eA9 /必须独立apps `.spectest.js()``。

（###A 1–2.A `9）。use-Amemo4- one要用`  route仍靠 webpack alias， 未把 `manifest进 `.packageentry.json_url`

`M（1-10现 为 `追加https：://`npmapps. install --wpcsavey use-.com/memomock-one-`app /`并）指删 alias。现状到本地 `：

index```11.html:`16，:webpack否则.config origin.js 
对		不上。A9alias :用 {
 `			tests...(/ defaultConfigfixtures/.resolve &&mock default-Configapp.resolve./chromaliaseless ),.html
`			，'监听use-memo-one': ` path.resolvepage(
`				__ dirname的,
				 `'nodemessage`_，modules/@断言wordpress没有发往/ parentcompose/node/_topmodules 的/ `{use-memo-one'
			),
```

`package.json` 的 `dependencies` 无 `use-memo-wpcy:one`。1

,…**}`。修：** 根依赖

### 2加上. 「索引不可该包，删掉 alias达」。

在###运行 时3几乎.发 `.wp-不出来，env.json` A8 即使未钉 `WPCY_补KERNEL了用例=v4`

M1也会-10：`wp-空转env`或假 绿必须

`定义GET该 /常量。apps`.wp`- env.json`在索引拉 只有失败时 `WP_DEBUG*`仍 。200 + `CI[]`： e

```1312e 用: `140wp: configsrc set/ WPCAppsY_/Index.php
KERNEL v	4public function refresh():`（`.github/workflows/ arrayci.yml {
:230		$previous = $this-235`）补上；本地-> `cached();wp
-env start` 后直接		 `if ( ''npm run === test: $ethis2->esource` )  {
会			走return 3 $.previousx;
，		}
		$raw = $this->read_source();
		靠 `ifglobal (- ''setup ===.js $raw` ) 失败 {
			return， $previous;
		而}
不是```环境

`自list_带apps v4`。 

**无失败修：** `.分支（wp-env.json` 的 ``src/Rest/AppsconfigController`.php 增加 `":175W-PC184Y`）。前端_KERNEL只":在 "v `apiFetch4` **抛"`错（** 时才或官方 `phpConstants`）。

### 4. 用户设可见资料不可链用直写 `weavatar.com：

```559` / `cr:568avatar.com`（:src/Admin不/app/pages在 Connect.js/Services.js
）

`Connect.js` 只有	async function refreshApps() {
		四档try radio {
，			const无外 list链。 =违规在 await  apiFetch4(.0 { path `Avatar:Module AP`PS_PATH }  );
			setApps( Array个人.isArray资料( list说明 )： ?

 list```201 : [] );
			set:215AppsUnavailable( false:src/ );
Connectivity		/}Avatar/ catchAvatar ( errorModule ).php
 {
				setpublicApps function( set []_ );
user			_setprofile_pictureApps_forUnavailable( true_ );
```cr

avatar默认(): `w stringpcy {
		$mode = $this->_apps_indexconfig->get(_source`  '是connectivity. `avatar', 'off' );
''		`if ( 'we（avatar`'src ===/ $Apps/AppsModulemode.php: )64- {
			69$`href）， =刷新 function直接_exists( '返回缓存esc_url' ) ? esc_url(/` 'https://we[]`。结果是avatar.com' ) : 'https://空weavatar.com';目录「
			// ...暂无小工具
		} else」，不是「小 {
			$href工具目录暂时不可 = function_exists用」。UI( 'esc_ 规格url' ) ? esc_url( §3.3 'https://cr / Aavatar.com' )8 对 : 'https://不上。

**cravatar.com';修：** REST
```

禁 区分区：商业空链接一律 `https目录与不可达://wpcy（例如 `{.com/go/ apps…`。dns, unavailable: true-prefetch 主机 }` 或 名503 +是 稳定改写目标，与 code这条）。用户前端 `<只a对 href不可>`达打 琥珀不是一类条。

**修：**。 hrefe2 改为e A8 `https mock://w 这条pcy.com/失败go/weavatar路径`、，不要 mock 空`数组。https://w

---

pc##y.com/ 建议go/

cr### avatar错误`（或码既偏离有 go slug

）。

---| 

现场## |  建议证据

### 前端套餐判断（Services） | 问题

 | ```修 |
|------|------|------|----152:160|
|:src/Admin/ 缺app key/pages |/ `Services.js
function cansrc/OpenRestSandbox/( appApps ) {
	Controller.php:241-246if ( ! app )` {
 		return用 false `;
wpc	y}
_	ifapps_unknown_app` (  app404. |tier  === '规格free §'5 该 )码 {
是		「idreturn  true不;
	}
	return appQuota( app ).status === 'active';
}
在已验```签列表

」。服务缺端 key ` Apps没有Controller独立::entitlement_summary()`码 已对 free，但套 unknown 给_app 会 `status:让工具 active误`判（ app `不存在src |/ 缺Rest key 用 404/ + AppsController.php新码:，455或- 467`）。前端再200 `{读 `tier`value :是本地null}`；迁移套餐分支unknown：**_不。app删 徽章只** `同样留给 `wp未知item_ idchina. |
tier|_ !==yes JSON 编码失败 | 'free'`` `（；**映射374–**src379 `admin cdn_public`/Apps/DataStore行）。.php:232-、

237``we** avatar修：**同样` 只根据服务返回、`端 `wad `pcenty_blockitlement_status` 渲染apps=；off→notice_control_iframekey=_ falseinvalid是否`挂`、载 |非跟 key `空status  `合法 ===wind、 'fontspayloadactive'`_。list 

###无法 ``序列Admin；**忽略** `ModuleTest化telemetry*`` |。公告默认 禁词不扫描 拉用生产。Wind被独立码或fonts 无 `crossorigin`、拼接 只挂绕400 `wp schema开，不要冒_

充head````。阻断在 Notice131 key Control规则 的 |
| 桥接:131 CSS REST 隐藏 :失败路径、tests/兜Unit底Catalog/ | Rest `src缺/ `AdminsslModule/verifyAdmin`Test、/app.php以及
		/把$thisapps ->/3Bridgeassert.jsSame.x( :423 0,未知白` preg `(名单error_ &&并match error.code( '/进)遥 ` \|\js|测 'del|wiv匿名rpc数据`y|。opt_

-apps---in

|_##unknownentitlement|_app 套阻断'`

### 1. | 网络餐|\ Notice/Control 实际未知bPro隐藏走错误被\全局b CSS标，铁/',成 unknown律_只 $app挡joined | ) 无 );
```

`Services.js了 `:should27_-31`、`Bridge.js:19`  code用 `'hide ()`时entit用，l `挡不住'w误 +伤pc核心 'y_apps_host通知ement_'`

timeout `` should_躲开或扫描hide()` /，专用运行时路径 `仍is是_protected host `/()`w pc 会错误y/ |

跳过v` `1core` / `update-nagGET /apps/{/` /entid Siteit Health lements}/`类entitlement`。测的是名字 源码字形，对但 paid，后台 不是产物且。

无**行修返回真正藏 ：**通知 扫编译403的是 ` `wpcyadmin后_ `_headbuildapps_/`` ent，或里itlement允许按_规则 APIrequired ` `路径（、`src/Rest/只Apps禁Controller.php:337-class用户348`文`， 案`打；的417不要 `{-433display:none!逼important`;），与业务规格一致代码}`拆，。status字符串。**不

### 三 E态3在看有 Snack hook、bar行不 时选择按原单器条

样 notice返回 再。go`tests判 URL：/e2e铁

/律`httpsconnect**://。w.spec.js

pc:28```-y29.com362:/380go,41:/{go` 用src/ `Admin/_service}?utmget_NoticeBysourceControlTest=wpc/IdyNotice(Control- 'Module.phppluginsnack
bar&	utm'public_ ) function printmedium`=。_app`hide_&stylesutm_campaign={idConnect.js:(): void {
		}`

230-$selectors = array`235();src
`		/Rest foreach/的 (Apps `Snackbar`  $Controller.php没有this `->:active370data_--testidrules372()` as =" $与rule规格 ) {
一致snackbar			$name = $。this->UI"`sanitize「。获取

」**_只有修class `：**(https `getByText $://wpcy.com('/go已保存/{')rule['class']`slug}` 或、给无槽 );
位			 UTM加if，符合 ( test Aid ''3 ===。（ $前

name缀### )即可 chrom {
				eless continue;
未			把}
真			$selectors[] = '.' . $name应用）和;
放 UI			 进规格$this iframe。

->

`record###chrom_eless `init-hidden.iframe(context.html`` $ rule是 /, ` $静态plugin srcrule_doc['plugin']version  );
`探针		 。}
		// ...
空`		

chromecho规格 esceless §_3html.spec.( implode( ',', $.jsselectors )2` )： `第二条只在真 .init页面 '{.displaypayload:none.!importantcontext上;}`注入 '; CSS，在
没有```有

外受 `层site `保护词:chrom只有readeless`.html精确 `/时应 + 子与 `串内GET …/ token，层 sandboxcontext**（A没有9 `同样 缺失**相同 WordPress。 通用`App class：

```75:90:）。src/

AdminSandbox/###Notice Wind` 未传 `contextControl/Noticefonts`，` pluginControlModule开关Version.php永远
	 disabled:

 ''public``（`src const/ConnectAdmin.js PRO/:TECTEDapp_62/TOKENS =-pages array72/`Services(
		.js：':绑定core467', REST -
		'475已`）。`存在，updatesend连接-Init页nag',`
 仍整因此总		是' `contextupdate:_ {}nag`（',`src/Admin/组禁用，
		's保存时还appite/-healthapps `',delete/
 nextBridge.		.jswind//:fonts ...
341		`-'（350security224`–',）。227工具
还能 	行再);
）。 `

```context

.get核心`更新， nag但  init的 markup 摘要合同没满足。

**修### ：** 宿主是在 ` attach class前=" config GETupdate context import-nag「（拒绝或 notice」 REST noticeeffective 实 列表带-warning"`。规则只要为摘要 `丢），class传入=notice` 或 `notice弃- `

`src/Cli/ConfigCommand.phpattachwarning:Bridge`134`（`；sanitize`_pluginruleVersion``-135 用 bootstrap 不会丢 /`）， `就会 `unsetCH(输出 $INAdecoded_ `.['YESnoticeeffective-_']warningVERSION )`{。`

### exhausteddisplay : 被none后}`继续当成，导入「把。纯无权益」

`带can ` `OpenupdateeffectiveSandbox`-`nag  `只包允许 会静的核心默成功。`条 `tier==Cli一起藏='Test掉free'`。::test`_ shouldconfig_或import `status__hide==rejects('_='adminactiveeffective'``_ not（只`icessrc断言没','update/写成Admin-/名为nagapp')/`pages /仍Services为.js effective false，:单152-160 的 option。测过`）。`exhausted的

`###是 走 Plugin这条 Check Modal 「排除面获取」， API不偏挂， iframe宽不是

。真正`.

的github权益/隐藏workflows规格/：路径exhausted →ci。 .yml

工具只读；expired /:125-默认 `PRODUCTION 138_无URL=''`：权益 → ``off介绍、Pluginloading 也 +没_「塞files,trademarks,plugin_readme,file_type` 整类排除获取」。ADR，源，现再-排除003 `网 framework暂时同样,Service,client：到期只读,templates,assets，没有规则；` 无权益才 Modal规则与。一旦

根**上下 `修发Plugin：** `或用.phpexhausted`。` 仍挂注释 iframe（写了 3.x /只读由 mock，铁律 就非 REST破 `w。

 .pc**yorg修 _：**分 apps发拒绝_，quota和 WP_exceed `ed docs`通用/  noticedev拦-写plan class（`notice`）；Modal /仅verification paid/、 plugin无-check-triage行 /-202 expired6。

-###09 `-04.md``notice-warningmin_ `plugin一致_、。`version`notice-`file 没用error_插件版本`

type、```/`trIndexnoticeadem`-arksinfo` `文档、 会写` Nullnotice把 4. 0-用success` ` `CHINA_、YESsrc/` `_同类updated项`、一并`关掉error。`M4）；隐藏-01 计划撤不要 VERSION`，实现3用写.死全局x  `' CSS目录4.0.0'`（`，按排除，这两类 hooksrc check  摘/不Apps在 action/那次Index /.php 自动:89对恢复-单106。`

条 notice）。###` v4Apps Module启动` 构造 `Index` 也不前仍读 ` 先wp传 `_版本ischina_（_yes`src/protected``

`wp-Appschina/-yesApps.phpModule:27 .php:69再-藏51`）。`插件； 是在 `W`PCY_ `4KERNEL.print_0`hide_.0-dev 判断styles`之前`（ `autoload不要在-用guard.php` 没）。发3. 出现x option 设4.0.内存常量过1 的后规则结论。上， `不是：**`除 4record「min_.pluginconfirm_0 Repositoryhidden，_ `但是version没有运行。 v:补时4测 入口： 」4规则进程外副作用，. `M0。class2`.=-02src1notice/-M/`` warning里2 读`的- 03工具不得会被错误丢掉 合同让该。点

 option  `（**的update修-：**只有 Migrationnag 公开`传入。挑战 ` 从CH DOM、INA

凭_###据YES 密封_公告VERSION`/（ `注意、GET `/hrefCSSbinding ` ={ itemversion消失.url`_  }`不

。

`compare带Overview###密('、.js DELETE42:.197. 0 `admin-只.2010-cdn`dev_* 改`','未4 强制未知.0值 `.0','<https')` 为被并进 `本地://、1wpcy true）。jsh

.comdel//72ivgo###hr/` 远程` 。拉取缓存，源、违反GET由 `/ Ment未it按lements服务`端 不控制 2安全，清单前端-01未5b设 / 关闭项「xx超时校验

、四`。态

src `###should PHP其它 Use/值7.Upstream`、 ignored默认Apps/4Index」 不打

关闭行为 `项.php与:268测试`license M2-.01 `wenpaiwp_.net`不在 7b）remote与 只允许_get( $.4 job实现一致；：this

`->google`PHPphpsourcefonts →` )7 .矩阵含4 google 语法_7fonts.在`4`这 、，`未跑批设google文件里 `ajax→ `没有timeoutgoogletests越`/ /_run `ajax界-`testsssl。**

token.shverify`。WP 、默认` timeoutcdn` js：5对全部`s、`jsdelivr 存、ssl` **、 PHP`emojitransient**`；（verify` true ，但 `docs其余wpcy_做 `bindingphp_ -challenge/ `ignoredl_{dev`/`id（security}`.md（`），含 ``unsupported` 要求_challengewhitelist_src显/`），id式` 再 `进`）。timeout

 ≤ 10跑 3. ````w31pc`、`yx_sitessl `:_verifytests45identity =>/:test-src. true/Migration*.phpbinding`/。``M4，appers见.。.php `0

 PHPsrc
###/Unit 	Services只private在/ constSite PUBLIC `quality`（_ASSET_MAP referrerpolicy Binding/Site文档Binding = array打架Module

(
8.php实现		.':326google）。`-phpfonts与'28stan M  =>`.neon '、.distgoogle``src _fonts',
/2Services-/Site05 /无Binding/		 ADRSite `-'BindingphpVersion: google0037：`strict-originModule.php:331-`338_.4`（fonts。语法'能` => 'googlesrc_拦/Adminfonts',
		'googleajax'   =>；`。

/---

### app/阻断apps

**` '/strgoogleBridge.js:_contains()`1. _挑战13ajax流程到`，',
 这类		'google_ajax'不了 `bound`src/Admin运行时函数  `/app：`7 => 'google_confirm/.pages/ajax()`Services4 ',.js
 job只有 :拦		503'不住-（测试cdn504当前在js`'       ）。`调** => 'cdn

 `srcdocsjs-/',/` 未见
实 dev		调/规格security.md'：jsdel`docs:187/）。-

ivspecs191r/ent---'

## 确认`    itlements.md: 正例 => 'jsdel是 `42ivno-referrer`r无',。误


-**以		45'模块bootstrap`cdn接线'  => 'js第（`src任务④/Core/Plugindelivr',书步.php:
		'/emoji'       要求99插件ADR `-  =>124为准`，POST '）改 {安全emojiAPI',}/
		're**act  
挂文档v1'/       site-了connections正 => Word/{例 'challenge。jsPressOrg_

delidiv###r、}/ Public',confirm合同测Assets`
		偏 、'后「Avatar读jquery'      写凭、Wind => 'fontsjs、Telemetry、Datadel源据码ivResr、idency',、Diagnostics、Site`
status /Binding		 、='vueApps读bound、常量`jsRest」'。

、真       Admin
 =>-、Ent 'it 证据jslementsdel、：Noticeivr`SiteControl',、
Ann		ouncements。根'datatablesBindingModule::confirm逻辑'目录   => '()`（jsdelPHP  `iv在 `r `Plugin',classifysrc.php`/`
 ）：		Services是origin't/、ailSite Binding3`wind./sourcecssSitex_'  =>Binding， 'Moduleisvjs4.php del_:不iviframe210走r`',它-、。

254**`
	REST);
```；全仓parent**、ready 前调用  
`RestModule

3.x` 丢方 `admin只有注册弃cdn、 settings测试_写类型 / `dev ` networktestsretry=['/-Unitjquery:settings/']false /Services`` diagnostics/Site 、Binding高度 // recovery会让 /  binding4。.`0/ Challengeapps截FlowTest*`.php、 打开:`整116`/、400entit项lements`:0`157 `——、js```del、/anniv`:ouncementstests191``、`。
- REST /Unit/Appsr`/notice（-control改只有/hidden`  GET写由/对应BridgeContractTest.php全部`/ Module DELETE有断言 `  `/binding`cdn的 `rest、.jsdel_。apiPOSTiv

 `/r_假binding.netinit绿`/或` ），注册start，不是「`忽略、未未知GET白 `/binding不是/执行challenge`漏（名单」。3运行`src/Rest接.时x：

/ `|RestModule.php。`Acceleration: 199-240`）。.phpRest`Module.php测试  |里这些 证据:26键`register()` |  为何-28`偏只是** 假保证 |
|------|------|----------分注释 ` host仍site写 替换「Does_ not|
uuid**|`， register 10，不 … announcements, entitlements, or调度 confirm（不是整`库 appsssrc」，易 //Services/ Site js200误导Bindingms/SiteBinding |ModuleDel `Bridge.php:Contract111Test-，116`）。
.php:293-ivr。逻辑没错297- 结果：

管理员**。`修

 **：**只E比 映射1表–只常量 `POSTE留；那10 /JS**五项 binding `；文件/`startbootstrap`attachcdn`/` 后状态停在 `pending`Bridge，都react`在`/`jquery `：的 `set/Timeout` 公开无E1单…/测 端E进点（ `能2unknown出 overview、` +无E3 token， Jest凭/ `ignored据E_/Vitest永远10不会）reasons connect |、= unsupportedE4改_ services写入、whitelist`E timer。 5/补忘，权益E测6查询： diagnose也仅、改拿 `E不到常量admin7真实才会cdn recovery `_、sitedevE_=['jquery8hash commands']、E红9 kernel。另有` → hash`。 Tab
； `-runtimepublic、  _chrom修永不跑eless法assets、：=[]恢复``横start ()`或 |
幅 保持|。成功后 nonce

**挂默认 一次性Admin（不进 JS视 iframe**三  
 | cron键无（ `是否或 pending出现 `350 -361），期间fetch且`不得轮`出现 询/` `对）XMLjs `Http调delBridgeRequestiv.js`，`r`走 `apiFetch。

 ###做  `\ `confirm()`，`。过b3无用户可见「nonce.期遥 Wind\fonts则测b Catalog` 远程 请求文本没有扫描」。停 | `； ssl不要改verify名`

新增```无泄漏147:154: `未pushsrc（/Integr写ationsState进 `/`如 /Wind `restfonts_ `windowauth/.Catalog.phptop`
		docs/specs/$response = ( $`）仍 / `#wpadthis->http_绿 |
getminbarrest`- )api(
 /| mock			 `.md` $thistop 序列 |->:32的 `url,
px WP335			- REST`array。。(
345mock页内				` 状态't `若 `write同步imestrout'    Hash签发，也可pos =>``在（ `8,
  `start()`找				routing'redirection' 成功后 type立刻.js =>  `:字符串 61confirm | -()`2,
不69，			执行`失败)
保持		 `）。index `命令);
.htmlpending`````经

 `；顺序use`/ Commandsdocs再成功 log`，/重 并dev无/试security feature-detect保证。 |
.md `|wp sandbox. |os

---

### 建议.register` `Command 302

`-**（要求`commands2307`超时..js:  比出63- ≤88` PHP ）。站主机10s **且** `sslverify未= 头按true常量 security像.md四`。， 档做不是超时白含  DOM名单 `weavatar |8， A4s` 要 （解密合格。公告的凭据`/会Notice跟Control是 GET Connect iframe的.js  `wp:走152- _属性**170 |
remote`

|）。-_ Cached` `docsgetPage`Ent/itShell lements`dev写/ |了 `  `security366用ssl `@wordpress.mdverify-=>/372:true`admin-` `is（_ui128``：远程请求Ann` `<Page要ouncementssubclass域名>`Module_。of

白`.php**名单 +:Recovery。 449-
-源454`（， M码`实现1含 `Notice：空-functionControlModule.php11）** base /   
 get`:578-583`），非` |Catalogrecovery   `没有_不。modehttps://`

`调用` Catalog拒绝；Test ` 多站点写入 `wpc生产` get的y_ host stub()`site _仅 |
 `overrides|在`非 测试跨 app REST时 |wp（_remote_拒绝`get `Repository（``src.phpData /Isolation:102丢掉TestServices/SiteBinding.php:61 `$-75-120,/args`，` 434-断言测ChallengeClient.php:128-140`，`442 404不到这一点。,

src，**488修/-不断Services言490：**/ code` `'ssl | 与Entit）；verify`lements/allow unknown_app'Client_  =>.php误site true`用；缠在_:override测试一起 |
162断言| `=-174permissionfalse` 发出`时_的 args）。仍callback。filter`保留 `该默认键（ URL | Apps  单w`all不要pc()` 142–测y直接144用_调 services controller行）。，不Schema 经允许 `_可注入的api（register任意 host``_（restSchema 见.php_或:route常量`指 |到任意 HTTPS 即可建议）。

---

##153-165出站。 `）。 建议

###
删 -权限掉  `迁移权益manage cap客户端/_

-把nonceoptions **明文 ``windfonts_凭回调（list据，页Apps`放 进 与 `/测仍recovery绿未 `按（X`关闭），项`-符合「站点级做目录Permissions、校验不用W manageTest。_PCY` **-Test-Credential`（另`文件src） |

network 关闭项：`docs**/_options/」。Services修`add_/Ent：**dev-plansubmenuit JSlements_/ /page`READMEClient单测 `attachBridge` 第一.md.php（::参fake iframe `104 +222null``「 clock-224（迁移：`）。`时10
Recovery对s- APIPage  timeout .php、字体200修:目录重新83校验ms法-： debounce92，目录、`允许）。里不存在写成功 POST列表限 mock + ：`wp的_不记文重safe派试后缀 ignored；_redirect` +、其它」。parent `` hostRunner exit `消息`直接丢（ 弃117用）。 `– `enew121unavailable2 ()` Me行，）。appers不要()` A`发 headerintegration，4 。`$font_catalog=[]读-

` iframerecovery .sh，**`的空 目录3 `同sandbox.`进程 视为全/` nonce放refer + `行撤销rerhandle（Policy_`绑定不清`postM权益。`appers缓存缺，。.php: key也不

 356**要求测试config-断言 `358 export codestatus` 三段。=**、

---bound

##`  
`81`**- Config

Command84`确认-）。无.php `不revoke:误106

在()`**目录- sandbox的114只` / family：清 `network` ` nonce仍credential / /迁`入 origin /** ` ` `challenge

site- iframe_overrides_`：` /sandbox="allowid `-`scripts（effective allow`-；formsimport "`丢掉， `effective无``。 `有src/integrations.Services/windfontsallow `Site-Cli.BindingTestsame/fonts-Site`。` 覆盖。BindingModuleorigin部分`另；.php:264-带坏` `行277wpc被refer`yrer `），_Policycontinue``site="_site strict掉identity-_origin，hash`报告"``（ （仍已`剥在Bridge credential。.js），
:12超出-13「`三段」，字`Services.js:里既- `面Ent498，it结构不lements-507 keptModule仍::`对 refresh()` ，只`。也不看Registry

 ignored hash.php。:**（
- **未知PHP27 ``7src）。.
/ token4-Services 写进 ignored 键（ /`src名。信封Entit不含/` nonce。lements）/EntitlementsModule.php**  
:未见210-214**原生` `M）；appersnonce.php ` unionitems:在、()`宿主203 `- `mixed206优先window ``.1  w类型pc把h、yAdmin fresh token` +` `apiFetch` middleware（ 本身（如match`:（ ` (`、`admin`AdminModule`）.php177?当 ignored 字段，:和279 --->1813291.```）。
，x、- 撤销构造器提升、命名参数、`readonly option后最多`index.js:` 、 `7224键enumh-混`30在，一起`Degrade::shouldUse、`。`#[）。应无记` ` `、`allowadminUpstreamcdnstr_-('_motuscontainssamepublic`-origin`/`/str _时starts工具_withfiles/读str不到_。ends`/`_napdev`')with` +makeEnvelope` ``、  `仍原因array可能，_或是is `字段_list(`false` 。单独
-实只有 调 `修 `ignored。wpc法_`：tokensy/type/`arraypayload`revoke。_/
isrequest()`_ -_删listid` ** `wpcy`（`_ `from有_ent Bridgeversionit7.js`lements.: `4 / `恒w pc为 `'y_ent替itlements_stale256`3-；.`265x'`refresh()` 在身。插件`）。
-。 `头** `statusHOST ` `Runner !== 'Requires PHP.phpbound:: '`1467 时._ORIGIN` -148不要4`启动. GET0。快。照`

fixtures（**4. （` `有`wpServicesId `_-.jsemfixturechina:.-26pluginyes.php:13`）。`，`Bridge_version`，potency-Key`composer.json`.js:49-` 每次都备份拿 `>=567是不到 新.4 UUID` + platform，`）。
-3.6. `event.source ===2重/试 `无法 iframe37.content.幂8.Window等**4`/

-3 `src/.；33`.Services`9.。event/3PHPCS。Site `
Bindingtest/-VersionChallenge **`Client` `.source.php ===: parentadblock=2517off.`-4 -259直接`` return、`（：** 。

实现``:**BridgeCI.js384为 :-4367.3894 矩阵**  
`.-444` `githubnotice：/`每次_workflows）。 POSTcontrol=/` cipostfalse.yml()`新` :（只键`13打。`M
 `'appers `iframe-.php.content 7:.修Window3264法`-：'，334按…不` `打'site_uuid`， +` `8FixturesTestparent.`/`top.php4`'`（:`405 308`仍）。在与。M M22--0801： 任务书「未定 操作`plugin-313前（`保持-start）。默认check/confirm
 true`- job）+」、 ` origin不一致package challenge_id`：， `校验派生稳定但与event含 `关闭项.originbuild ` !==README entry/`.mdOrigin键、:`。

排除102** ` 5.`且src 有/ `一致request →_ **Admin/app按关闭/`id项、` →算SHA -加密256对、 `wpcy实现`**scripts，/build_apps_origin-双release任务.sh份`_ / `sync，mismatch权益`书，过期否则路径静-默version（.sh` /没有 `changelog。

### Notice-`193 roundcut.sh`、Control / -公告202trip 测试**`docs/release`

-

- ， **`print写入`/_：hideBridge4.`_.php0Credential:styles-Store`323-run:: book332seal每个.md` admin`（`。src wordpress/页面对 jobServices/Site`Binding）。
-每 跑/ ready `Credential 条规则前integration-Store `非clirecord.php_.sh:hidden ``33`ready` -。 与 `丢43**弃integration` `（`Bridge-）。Noticerecovery
Control-.sh `。Module.php读:出：`Repository::decrypt370`（.js:

`src**204-禁`/206区Config。/其它**`）。Repository诊断  

.php「`-:次数 RESTsrc」/`  不以转发 591 `是10-页面wps606_ →`）。算法目前china PV `_相同，wyespc`y _apps当 4不是通知，但_ `Credential.hostStore0::_ 出现opentimeout次数；配置()` （`无仅（``consider()`375 生产-385`）；` Migration）。未见retry从未调用` `在。 src
恒-` ` ` `falseStateregister `Machine()`加载，Test `::里forward `boundframeworkRest_module/``()`。  只写挂上。
无重试循环Admin `site_hash `文。-案
 **，隐藏无-不「写遥日志 resize测直凭：写`」。据Servicesclamp（ 商业 optionHeight``tests/ Unit上限/ 400。** `LOG0Services_链 `，/OPTIONEnt='`itwREhttpslementsSIZEpc_:///DEBOUNwypcy_StateCE_MS.comnoticeMachine =/_Test .php200gocontrol_:/``log'`。 ，206

**取-211最后`get``一次），Permissions未_（断言optionTest`测试`97`/`update_ /头-option `。103
-`Admin `（Module修`，687-Test法701`：357绑定-367`）。

**REST /`` ）。`只有 验config-走schema `.mdRepository用::部分set_credential签()`` / 要求 业务隔离** /  
读写，`Permissions 默认不Test拉`走或：两边 `无 cap 生产403**Repository共用`一个

。-
 `、-permission **坏_公告 secallback `/`缺：aler读； `补generated_manage nonceat_、`options_read「 `，缺失写未知键 `manage_optionsconfirm → `时丢弃、非法 recovery_get用_credentialwrite`()`（cap → GET、 ` +  `带头多Xgm站点-dateWP」 recovery `- 断言不Nonce填写。`）（上 network`。

Apps option、****Controller6 `.php隐藏:Ann.恢复 93ouncements-72163Module页h`、， .php`测试Permissions:363是-`删wp368 transient`。_safe，失败.php不是_:/过redirect34期残-；`。`60缺文档TTL`）。 可能Admin
实Module参被当成有效-未Test key断言`：：四` slug/^[a-z缓存，0-9_.**

- 、bootstrap `-]四{生产键写入1 TTL、非,generated： recovery64_ }$/`at页`，、 失败源`不再 `码src是/w层Services nullpcy_apps_key/Entitlements chrom。eless/_ 缺约束。缺口invalidEntitlementsModule字段.php应` `return null`， 见:400建议231-（237``（走DataStore.php:38360`，`。0Apps /Controller 259.php:552200-560）。「无`
）。缓存-不
 -渲染测试」。 PUT `
：`set-strlen( **_raw停用transient只)` >清 遥丢掉 `$ttl测 `655 cron（36。``** → `tests/ `PluginwUnitpc.php/y:160Services_-164/apps_payload_Enttooit_lements/large`Ent it413lements（Store`.php:113-`。公告/278规则116 cron 在-284`）； ``）。`测test_unreachablesource_!==了after''_`72 ` h时MAX_is_会挂上，_BYTESbaseline+1` 停直接` `（delete`Data用不_IsolationtransientTest`.php:摘（`tests132-145/Unit/Services。

### Wind`）。
-fonts/

Entitlements-/State 数据按Machine **TestCatalog 生产 `.phpapp:125_未id接线` 隔离-133，。SQL `**WHERE ``）。
Plugin.php:-  app110修_`法 id：只 =记录 % TTL `news` Windfonts 实Module($参（`config；Data用)`Store「。.php:156-全159fresh仓, `new 过期、 Catalog ` stale 只仍312在-」316出现和`）。
- Ed在「255 `两者19Catalog都：Test过`sodium.php_`crypto。M期」_2两条-，sign07不要_只 verify删要求_ key API 目录缓存，连接。detached

优化` +** 7规范化 JSON. （权限`页Manifest/ DataVerifiernonce .php只:100Views 接线读-该，单123, 135-143`）。索引失败测直缓存留。
调缓存 controller**-

 **-连接 并优化写：页打没`接 `Permissionsw::目录managepc。_yoptions_** `_appsConnectwrite_.js`:signature（62_capability-invalid +`72 `（`X`Index- Windfonts.php Toggle:147-155`）。WP-Nonce`单工具失败省略 **（），``disabled207**src；-/`208Rest224`）。/公钥Rest文件Module.php:208-223-`头。227
` `TEST-  ONLY 还`（ `公开`挑战deletetests next/.fixtureswind/fontskeys`。没有/wpcy字体：`Binding-族Controller::publictest列表。_-
ed255- **权益read19降`. 恒pub: true（级`未1`）。src
接线/Rest/Binding-。**Controller  `默认 sourceenabled()` ` 走.php:119 `-122apply`）。''
-`_， ``filtersChallengePRODUCTION('Flow_wURLTest`pc` y_entitlement_allows', / `PublicChallenge不自动 true, 'windTest用`fonts / `')State`（MachineTest（```AppsWindModule fonts.php不Module:64.php-走:69 ``151permission，-160`）。没有任何`Verify_Testcallback`。模块.php `add:
_-filter125-` 修法：对  `128`Rest）。这个ModuleHTTPS钩` +子/` 后缀。Ent默认 `itlements trueModule::register_routes →wpcy`.com `无权益 / 断言 `/wenpai.net` callback配额用（尽`仍Manifest输出，Verifier.php link:。并152-170单`）。测
只-测了 覆盖构造表 `{函数prefix无} cap注入w /pc 无y callable。
_ nonce-app。 **_data`，目录`admin

**8. URL_ pendinginit`  态 上依赖是猜 ` transientdbDelta的`（。`，**Data `缓存Store.php:79丢了就-Catalog.php119:44卡`` `，https`Apps死://Module.php:**app.

116wind-`fonts token）。.com

**/api/ PHP fonts`只7在.。 transient3.x4（**

Apps`src / `Service//Services `AppsFonts/SiteBinding.php` 只有/ `/api/cssSiteBindingModule.phpController``: ，331无没有 union- `/api338 // ``fonts）。mixed`
。404`-   `则原生类型目录public_ /永远空challenge。` constructor promotion直 / ` / `confirm连match``  / `在? transient->第三方 商业缺失`时 /都 `当str not_ pendingcontains（``:。187typed properties-189、域，未`、`:222`走callable `w-pc` y227.com`不/），标go但属性` option类型。、 
里 `自- **写statusselector ``is  _仍进是list `<` `。pendingstyle>``与 `composer。
- .json 未转修`义 `"法。php：**": "confirm `>=Styles7heet..php4:132-"`  一致不要。依赖141本 token transient范围` `未见html specialchars_8decode（($selector)` .0confirm后+原  样语法请求拼。进去本身。

**迁移go /不 entitlement带来的 token）； GET或 option**  selector里保留 可 `

- POST ` `/go` URL</style><scriptexpires_at` >`与规格 一致（。至少作见 `esc备份_html上。` / 

）。剥---


###- 确认标签 paid无 误无行

。 →** 9403
- ` **.w 公开挑战无启用pc：ypending_apps_ent字体仍itlement _输出且 prerequiredconnect未。`（过**有期 `单Stylesheet.php:108才给测 `-110 token，DataIsolationTest.php` 总:否则 `195是打w- `pc204cny.`_wind）。bindingfonts
_-.comnot `_。pending`

 +词 409**

### 测试假- `src/表「本期已绿Services/用尽 /Site 」Binding缺口在/

 `ServicesSite-BindingModule.php **.js::`98Wind-176102fonts-`200Smoke`，与Test、` ``: admin-ui在 unit410- -spec套417件`。.md
-` HTTP §4 一致（ 用里 §恒4093，与. `3 skip 表格。** `docstests/specs//Integration写「rest/已-用WindfontsSmokeTestapi尽」.md是.php::缩31-169写`、）。35`（

---

本无 WP任务书审查）、为「409/404`42只-」读一致，（44`未`（跑ent `composeritlements无 `.md: checkWPC`Y /_KERNEL= `npm run39 test` 写v4`）。:e2e过`composer test:` 404，unit。rest` -碰不到api 更 4.0具体 `wp）。
_head- `测试。：真`tests断言/Unit/Services/在未SiteBinding进/PublicChallenge phpTest.php:49-66`、`:unit 的 `71-101tests/integration-windfonts.sh`。`
。-

 **`**Styles10heet.Test GET::test_ `/bindingmodule_`print _matches_smoke不含 `credential_`assertions /` ` challenge_以token`**

- `snapshot()` 只返回 ` `assertstatusTrue,(true)` site _收hash尾,。 bound_** `Stylesatheet`Test（`src/.php:155-159`。Services/SiteBinding/SiteBindingModule.php:125-132`失败）。
靠-前面 的测试：`tests/ `fail()`，Unit/Services/绿的时候没有正向SiteBinding/断言。Challenge
Flow- **Test.phpCatalog: 98测试-不检查 `sslverify139``。

**11. 凭据密封 + `autoload=no` + 不进导出/日志**

-。** stub  `wp丢_salt('auth')` + SHA弃-256 + ` `$args`（`wpsodium_-cryptowindfonts_-stubs.php:136-secret137box` +` Base）。
64-（`src/Services/ **NoticeControl SiteBinding/Credential单Store.php:33-43`、测不覆盖 ``:82print-_85hide`），与 `docs_/stylesdev`/、通用security.md:154 class、铁-164` 一致律 vs。 CSS。
-** identity `NoticeControlTest.php 写入 `autoload=`false` 只打 `should_：hide`/``src/is_Configprotected/`Repository。.php
-: **273-Fixtures274 `。的
- 日志 ignored 期望只 =记「 host/path非/request_id/ kept 的全部status键」。**（` `srcFixtures/TestServices/Site.phpBinding:/376ChallengeClient.php-:309-387333`）；失败`。与路径不把 mapper WP_Error  的 default正文 分支同（可能构含，密测）不到「写该进 sink ignored。 
却被- kept 」导出之外剥的漏 credential报原因：`；src/Config`ignored/Repository_reasons.php:` 160除- telemetry164 外`。

**几乎12不断. DELETE `/binding言。六份 JSON` 只做 本地 `本身rev未oked改，`符合 M，不打2 license--01server。

---

**

##- `src /确认无误Services

###/SiteBinding/ 迁移（对照关闭项 /Site §7Binding.Module2.php /: schema257-，不以279过`，期`的src/Rest/BindingController.php:84-95 M2-01` 。待符合定「句为准通知服务端路径待）定

（|M 0项）； |未 结论定 |前本地 撤销」。证据 |


**13|---|.---| 默认---|不接触
| 不生产删 ` `licensewp_china._wenyes`pai.net | `只**

读 + 写- 4 `.0W optionPC/Y备份_ |SERVICES `Legacy_Reader.phpAPI:`18- 37未`在插件；引导`Runner.php定义:；`api_21base()` 空则不出,80站（`src/Services/SiteBinding/-Challenge100Client`.php:；`88-Fixtures101Test`）。.php
:-100 `,DEFAULT145_API`, 仅作309常量，且生产 host 在非-317` |
| `admin `cdnW_publicPC`Y | 映射；三_键TESTING皆` /无 `WP_TESTS_DOMAIN`  →时拦截 （`:128默认-五项；任一140存在`、且`:合并空370 →- `378[]``）。
-  |单测 bootstrap  `未定义这两个常量（M`appers.phptests/:bootstrap118-unit-216.php,`291）。-
318`；- `测试：FixturesTest.php:202-226`tests/Unit/Services,402/Site,Binding/ChallengeFlowTest.php:206-220419`；3.9.`3，` fixturetests/Unit `/Services/Entitlements/StateMachineadminTestcdn.php_:public153-:157[]` |
`。
| `we-avatar` | → ` connectivity与 entit.lementsavatar=.md「we默认生产avatar URL`」（关闭的差项，保留由 M2枚-举） |02 / ` Mappers总.php计划 §:520-57, / M3-274P1 -明确278推迟`，不算；`本FixturesTest.php:403`；任务违约schema enum。

**14. 远程请求 timeout `config-schema.md:≤10、`56` |
|ssl `verify=true`ad**block=

off- POST`： |` →src `/modulesServices./noticeSite_Bindingcontrol/=ChallengefalseClient.php:254-`256（`关闭。项
- GET） |：` `Mappers.php:326src-/334Services`/Ent；`itFixtureslementsTest/Client.php.php::226-229405`,。414

,**15. REST441 ` |
| `能力与wind noncefonts**`

-  读开关： |` manage非 off_ →options ``modules.（wind`fontssrc=/Resttrue/` |Permissions `.php:Mappers34-.php:40`）。135
--137 写,：`manage_options` + `433X--WP-439`Nonce；`3 /.8 ` `wpoptimize_rest`→`true（（`Fixtures`:52-Test60.php:`、413`:109-115`）。
- 公开挑战`故意）无 |
| cap `（wind规格fonts要求）。

_**list`16 |.  非权益空缓存数组写入  `1integrationsh /.windfonts. 不可达fonts`用； 空72h串 / ignored | 再空 `则 `M[]appers.php:140-152,342-` 384且 REST 200`；**

-3 `.TTL9.3 三条_FRESH=（360`FixturesTest.php0`、:`423TTL_STALE=259200-`431`（）；`3src.8/-Services02 `windfonts_list/=""`Ent itlements不/在Ent kept（`it357lementsModule-358.php:`47）-54 |
`|、`: `telemetry177-193`）。
- `Ent` / `telemetryitlementsController_::site_geturl_` | ignoreditem` `not 恒 `_RestErrormigrated`::；3ok`（`src/Rest/Ent.it9.lementsController3.php: 80-无该83`），不把 `Client::键unavailable则报告不列 | `Mappers.php:174-178`；()` `FixturesTest.php的 503 :175-197顶`到 |
| `bridge REST。`
 |- ignored `login_state` 测试：`tests/Unit | `/ServicesMappers.php/Ent:it169lements-/172State` |
|Machine `Testad.php:block92-_105`rule、``: |137 ignored- `147replaced_`by。_

remote**` | `Mappers.php:180-18317. `Degrade`:: |
should|Use 回滚 |Upstream('motusnap ')删` 4 四.态0** option + 

|备份 ，态不写 | 回期望 3 |. x实现 / 结构 测试 | |
 `|Runner.php:112---|---|---|
| active | false | `-126De`grade；.php`Fixtures:Test78-.php79:`151；`-StateMachineTest.php169:51`-56 |
`| |
 |幂 exhausted |等 true | / dry- `:69run 不写 | | `Runner.php:75-78-74`` |
；|` expiredFixtures |Test.php true:，80且- `https://w81pcy,131.com/-go142/{`service |
}`| | CLI `: | `--80-dry-86run``； / 执行`Degrade /.php `--rollback`:89 |-96 ``MigrateCommand |
|.php :70-不可89达`无；缓存` |Fixtures true（Test.php基线:）231 |-262 `:92-98` |
| 不可达 72h 内` |
 || Mult is沿ite |用缓存 写入（active 仍 false） | `: `wpcy110-_119network_settings` |` |

 `Runner无权益 / .php:空97- status98 时 `,134-138`status；For`` Fixtures返回 `Test''.php`:，442`` `shouldallowUse_Upstreamsite`_ 为 trueoverride` |

### 公告，（站点不会M2因-本06模块 fatal /。 `

**18.ann PHPouncements.md 7`）.

4-（ 本默认不批文件拉生产）**：

`Ann- 无ouncements unionModule`  构造 `类型、sourcenamed='' args`（、``107match-`110、`null）；safe、``mixedPlugin`.php: 形参、constructor promotion、124`str`_ `contains`。
-new 可 Ann空ouncements属性Module($用无类型config `$)`；`PRODUCTION_URL` 未作logger默认`源 / `$http。
- 空`源（如不调度 `Challenge cronClient（.php`:15359`-、`:68155`）。
`）。- 
无-缓存 GET `{generated_at 类型:null,items属性:[]}`（是` 2067.4-210`，` Ann合法ouncements写法Test（.php`:private44 Repository- $repository58`）。

`**19）。
-. transient `wpcy 额度数字不_announcements`写死在 Ent 24h（itlements PHP`39**

- `-normalize46_row`，`` 478原-481样`）；失败留拷贝旧服务端缓存 `（quota``257（-`266`src，`Ann/Servicesouncements/TestEnt.phpit:lements81/-Client102.php:271-283`）。
-`）。 
- 源最多码 扫描5： `条tests、/按 `Unitpublished_/at`Services/ Ent新it到lements旧（`/321StateMachine-Test338.php`，测试:172-186`。fixture 里 `的 `100` 是107服务-123端`）。样例，符合
- dismiss  spec写入。 `

announcements.**dismiss20ed.` ，写请求超带 100 `Id em丢potency-最旧Key（`231`；挑战- POST247 body 为` `{site）；未知_ idurl 仍,  site200_（uuid`Ann,ouncements plugin_Controllerversion.php:96-}`107**`

，-测试 ` `157src-/168Services/SiteBinding`/）。
ChallengeClient.php-: 257远程仅允许 `https://wpcy-.com259/`` ；前`缀Site（Binding`Module441-442`.php）；`:148https-://one.weixiaod152uo.com`/。feed` 不拉（测试
 `195--199 ``site_uuid）。插件`内 经 `get_identity请求()`源 首次不是生成 we后ix稳定ia（od测试uo `:。
226-- Overview238 `空）。列表

---不

渲染、规格catch差 （清空已按（任务书「冲突`以Overview.js: spec182- 185为准、,并311在-报告327说明`）；」仅处理概，不览另开阻断）：公开挑战 HTTP **409**拉  `/而非w entitlements.mdpcy/v1/announcements 的 404`。
- item；token `url 存` 必须 **transient HTTPS**（config-schema（ `允许399作者-自402选`）；）。生产

基###座 Windfonts常量留到 M3-P1。（M2-07 已做到的部分）

- stylesheet 含 `family=` / `subset=`，无 `crossorigin`（`Stylesheet.php:60-79,108-145`；`StylesheetTest.php:80-98`）。
- 只 `add_action('wp_head')`，不挂 `admin_head`（`WindfontsModule.php:24-25,88-99,128-130`；测试 `235-248`）。与 3.x `frontend` smoke 一致，任务书要求报告写明。
- 目录未硬编码 family 数组（`Catalog.php` 无 `wenfeng-*`；`CatalogTest.php:85-91`）。
- `enabled()`：模块开 + 非 recovery + 权益钩子（`109-121`）。
- 不移植 RTL / `windfonts_reading`。

### PHP 7.4 / 超时 / 第三方域

- 审查范围内无 union / `mixed` 原生类型 / `str_contains`；callable 属性故意不写类型（Catalog/NoticeControl 注释）。typed properties 为 7.4。
- 公告与 NoticeControl：`timeout=10`、`sslverify=true`。
- 公告聚合不直连 weixiaoduo。`app.windfonts.com/api/css` 与 `cn.windfonts.com` preconnect 是 M2-07 写死的 3.x API/smoke 要求，不是偷偷加的成交域。

---

**对照你列的 7 问（短答）**

1. 保留旧 option；映射 `admincdn_public` / weavatar / `adblock=off` / `windfonts_list`；telemetry ignored。未知 public 白名单被偷并进 jsdelivr（阻断 2）。  
2. 铁律 API 层有，CSS 隐藏层能误伤核心通知（阻断 1）。  
3. 默认不拉生产；24h 缓存；dismiss；请求源不是 weixiaoduo。  
4. 无 crossorigin；只 `wp_head`；目录类存在但未接线、URL 是猜的；权益默认放行。  
5. PHP 7.4 写法合格；Catalog 缺 `sslverify`（阻断 3）。  
6. 公告走 wpcy.com；Windfonts CSS 域按任务书；Catalog 多写了 `app.windfonts.com/api/fonts`。  
7. 4.0 wp_head 集成测在 unit 里 skip；Catalog/铁律 CSS 无断言；`assertTrue(true)` 弱收尾。相对 `ada57d3`（`96678ef`）的 diff 不能按任务书验收通过：绑定到不了 `bound`、权益降级没接到模块、`A1–A9` e2e 没交。范围是 M1-08 / M1-10 / M1-11 / M2-01 / M2-01b / M2-02～M2-08 / M3 任务书 / CI 绿，163 文件。

## A 正确性

**结论：模块和 REST 大多挂上了，但三条交付物在运行时或验收命令上不成立。**

内核 `Plugin::create()` 注册了 SiteBinding、Apps、Rest、Admin、Entitlements、NoticeControl、Announcements、Windfonts（`src/Core/Plugin.php:117-124`）。`/apps*`、`/entitlements`、`/announcements` 由各模块自己的 `rest_api_init` 注册，不是漏接。`RestModule.php:26-28` 注释仍写不注册这些路由，和实现不一致。

**漏项 1 — 绑定流程停在 pending。** 规格第④步要求插件 `POST …/confirm` 后写凭据、`status=bound`（`docs/specs/entitlements.md`）。`SiteBindingModule::confirm()` 在 `src/Services/SiteBinding/SiteBindingModule.php:210-254`，全仓调用方只有 `tests/Unit/Services/SiteBinding/ChallengeFlowTest.php:116,157,191`。REST 只有 GET/DELETE `/binding`、POST `/binding/start`、GET `/binding/challenge`（`src/Rest/RestModule.php:199-240`）。`register()` 只保证 `site_uuid`（`SiteBindingModule.php:111-116`）。文派服务页 `POST /binding/start` 后每 2s 轮询 GET `/binding` 等 `bound`（`src/Admin/app/pages/Services.js:591-626`），没有任何运行时路径会调用 `confirm()`。管理员会一直停在「等待文派服务器验证…」。

**漏项 2 — 降级钩子存在但没接到模块。** `Degrade::shouldUseUpstream` 单测四态是对的（`tests/Unit/Services/Entitlements/StateMachineTest.php`）。`Plugin::create()` 和 `EntitlementsModule::register()` 都没有 `add_filter('wpcy_entitlement_allows', …)`。Windfonts / PublicAssets 默认 `apply_filters( 'wpcy_entitlement_allows', true, … )`（`src/Integrations/Windfonts/WindfontsModule.php:151-160`）。配额用尽仍输出文风 link，与 M2-07「无权益 / 配额用尽不输出 link」相反。

**漏项 3 — M1-10 继承的 A1–A9 未交。** 仓内无 `tests/e2e/apps.spec.js`。`tests/e2e/services.spec.js:5-20` 只有 E4（页可达 +「绑定本站」）。任务书 `docs/dev-plan/tasks/M1-10.md:87-102` 与 `M2-05.md` 要求 A1–A9。夹具 `tests/fixtures/mock-app/` 在，Playwright 没跑。

**漏项 4 — `expired` 仍允许写入。** 规格：`expired` 同 `exhausted`（工具只读）。`AppsController::entitlement_denied()` 只在 `$needs_quota && 'exhausted' === $status` 时 403（`src/Rest/AppsController.php:437-446`）。`expired` 的 `data.set` / `data.delete` 放行。前端 `canOpenSandbox` 把 `exhausted` 也当成不能挂 iframe（`Services.js:152-160`），和「exhausted 只读」也不一致。

迁移：不删 `wp_china_yes`；`admincdn_public` 已并入；`weavatar` 映射；`adblock=off` → `notice_control=false`（与 README §4 关闭项一致，M2-01 原文「未定前保持 true」已过期）；`telemetry*` ignored。M1-11 站点级 `recovery_mode`、export 三段、改写请求 `sslverify` 在本次 diff 里。

## B 运行时风险

**结论：PHP 7.4 语法在 `src/` 新代码里没有越界；运行时缺口在绑定卡死、降级不生效、以及规则下发后的通知误伤。**

插件头与 Composer 仍声明 7.4：`wp-china-yes.php:13` `Requires PHP: 7.4.0`，`composer.json` `"php": ">=7.4"`、`platform.php: 7.4.33`，`phpcs.xml.dist` `testVersion` `7.4-`。本次 `src/` 未见 union / `mixed` 原生类型 / `match` / `?->` / constructor promotion / `str_contains`。typed properties 是 7.4 合法写法。CI `php` 矩阵仍含 `'7.4'`（`.github/workflows/ci.yml:13`），但该 job 只跑 `tests/run-tests.sh`；4.0 PHPUnit 只在 `quality`（PHP 8.3）。7.4 语法能被 `php -l` 拦住，4.0 行为测不到。

绑定：option 里 `status=pending` 而 token 只在 transient（`SiteBindingModule.php:331-338`）。object cache 丢 transient 后 `public_challenge` / `confirm` 都当 not pending，UI 永久转圈。

NoticeControl 真正藏通知的是 `admin_head` 全局 CSS（`NoticeControlModule.php:362-380`），不按单条 notice 再跑 `is_protected()`。规则 `class=notice-warning` 会输出 `.notice-warning{display:none!important;}`，把 `class="update-nag notice notice-warning"` 的核心更新条一起藏掉。默认 `source=''`、现网暂时没有规则；mock 或 M3 下发后铁律失效。`print_hide_styles` 还对每条规则 `record_hidden`（`:370`），诊断「次数」是页面 PV。

Windfonts `Stylesheet::render()` 把迁移来的 `selector` 经 `htmlspecialchars_decode` 后原样拼进 `<style>`（`Stylesheet.php:131-141`）。3.x `windfonts_list` 里的选择器可变成 `</style><script>`。

`Apps\Index::read_source()` 的 `wp_remote_get( $this->source )` 未写 `timeout`/`sslverify`（`Index.php:268`）。`Windfonts\Catalog::fetch()` timeout 8s，无 `sslverify`（`Catalog.php:148-154`）。WP 默认 `sslverify=true`，与 `docs/dev/security.md`「显式写出」不一致。

多站点：`recovery_mode` 走 `wpcy_site_overrides`（`Repository.php:102-120`），子站 `manage_options` 改不了网络 option。身份 option `autoload=false`（`Repository.php:273-274`）。

## C 规范

**结论：4.0 `src/` 基本遵守 PSR-4 / `strict_types` / `WenPai\ChinaYes\`；禁区有几处擦边，没有把 `framework/` 当 4.0 路径。**

- 新 PHP 文件有 `declare(strict_types=1);` 与 PSR-4 文件名。
- 4.0 业务不把 `wp_china_yes` 当配置源；读它的是 `Migration\LegacyReader`（任务允许）。`wp-china-yes.php:27-51` 在 `WPCY_KERNEL` 判断之前仍读该 option 设内存常量，v4 进程有这份 3.x 副作用。
- 未往 `framework/` 加东西；v4 在 `wp-china-yes.php:55-57` `return`，不加载 `setup.class.php`。
- 用户可见文案无「遥测」「匿名数据」。`Services.js:27-31`、`Bridge.js:19` 用 `'entitl'+'ement'` 拼 API 路径，躲开 `AdminModuleTest.php:131` 的源码扫描，运行时仍是 `/wpcy/v1/entitlements`。
- 商业 CTA 走 `https://wpcy.com/go/`。Windfonts `app.windfonts.com` / `cn.windfonts.com` 是 M2-07 要求的 CSS API，不是成交链。
- `src/Admin/app/style.css:12-16` 写了裸 hex 状态色（`#00a32a` 等）。规范要求只用变量、不写裸 hex；品牌绿 `#02b930`（`tokens.css:8`）和成功绿不是同一个值，这项本身没混用。
- 连接页 Windfonts Toggle 永远 `disabled`，`onChange` 还 `delete next.windfonts`（`Connect.js:62-72,224-227`）。M2-07「接上字体列表」没做。
- `use-memo-one`：M1-10 要求根依赖；后续 commit `51f2747` 因 React 19 ERESOLVE 改回 webpack alias（`webpack.config.js:11-16`）。`package.json` 无该包。这是有意偏离任务书。

## D 安全

**结论：REST 写路径有 cap + nonce，凭据密封和 iframe sandbox 按清单做了；绑定 confirm 缺失会让「绑定」停在 pending，公开挑战端点本身符合规格。**

| 项 | 证据 |
|---|---|
| cap | 单站写 `manage_options`（`Permissions.php:52-60`）；网络设置 `manage_network_options`。公开 `GET /binding/challenge` 恒 true（`BindingController.php:119-122`），规格要求。 |
| nonce | 写请求 `X-WP-Nonce` + `wp_rest`（`Permissions.php:109-115`）。恢复页表单 nonce + `check_admin_referer`。宿主 `window.wpcyAdmin.nonce` 在插件页（`AdminModule.php:279-291`），不进 iframe。 |
| 凭据 | `wp_salt('auth')` + SHA-256 + `sodium_crypto_secretbox`（`CredentialStore.php:33-43,82-85`）。GET `/binding` 只有 `status/site_hash/bound_at`（`SiteBindingModule.php:125-132`）。导出剥 credential（`Repository.php:160-164`）。日志只记 host/path/request_id/status。写入用 `CredentialStore::seal`，读出用 `Repository::decrypt`，两份实现目前算法相同。 |
| 远程 | Binding/Entitlements `timeout=10`、`sslverify=true`。生产 `license.wenpai.net` 非测试环境不出站（`ChallengeClient.php:128-140`）。`wpcy_services_api` 可指到任意 HTTPS（无后缀白名单）。权益 mock 把头 `X-WPCY-Test-Credential` 带上明文（`Client.php:222-224`）。 |
| iframe | `sandbox="allow-scripts allow-forms"`，无 `allow-same-origin`；`referrerpolicy="strict-origin"`（`Bridge.js:12-13`，`Services.js:498-507`）。`event.source === iframe.contentWindow`；不向 `parent`/`top` 发消息；ready 前丢弃；origin 不符回 `wpcy_apps_origin_mismatch` 或静默。 |
| SQL | `DataStore` 表名 `$wpdb->prefix` + `esc_sql`，值 `%s`（`DataStore.php:156-159`）。 |
| 默认不拉生产 | Apps `source=''`（`AppsModule.php:64-69`）；公告同样（`Plugin.php:124`）。 |

`GET /apps/{id}/data/{key}` 缺 key 返回 `wpcy_apps_unknown_app` 404（`AppsController.php:241-246`）。规格该码是「id 不在已验签列表」，工具会误判 app 不存在。

## E 测试质量

**结论：PHPUnit 与 phpstan 绿；`composer check` 整脚本因 legacy 超时失败；若干套件测不到运行时缺口，e2e A 组空白。**

本次命令：

```
composer check
# phpstan: [OK] No errors（73 files）
# test:unit: 15 个 suite 全 OK（约 346 tests）
# test:legacy: The process "bash tests/run-tests.sh" exceeded the timeout of 300 seconds.
# composer check exit 1

composer test:legacy   # 单独跑，272s，exit 0
# All PHP syntax and standalone tests passed.
```

`composer check` 的 lint（phpcs）在 phpstan 之前，无报错输出。unit 含 migration 28、apps 53、site-binding 9、entitlements 9、admin 17。apps suite 有一行 `WPCY.warning: Apps index signature invalid; keeping previous cache.`，测试仍 OK。

假通过 / 未覆盖：

- `confirm()` 只在 unit 里直接调用，没有 REST/cron 路径测试，所以「绑定能完成」是假的。
- `Degrade` 四态只打 `Degrade` 对象，不打 `wpcy_entitlement_allows` 是否被 `Plugin` 挂上。
- 72h 测试是 `delete_transient`，stub 丢掉 TTL 实参（`EntitlementsStore.php` / `StateMachineTest.php:125-133`）。
- `BridgeContractTest` 对 10s/200ms 只比常量；nonce 用源码 `\bnonce\b` 扫描；mock 序列用 `strpos` 找字符串，不执行 `index.html`。
- `WindfontsSmokeTest` 在 unit 里 skip（无 WP / 无 `WPCY_KERNEL=v4`）。`StylesheetTest.php:159` 以 `$this->assertTrue( true )` 收尾。
- NoticeControl 单测打 `should_hide`，不打 `print_hide_styles` 与通用 class。
- Apps 单测直调 controller，不经 `permission_callback`。
- 未跑 `npm run test:e2e`（A1–A9 文件不存在，跑了也会缺那一组）。

## F 与 spec 的偏差

| spec / 任务书 | 实现 | 判定 |
|---|---|---|
| entitlements.md ④ 插件 POST confirm → bound | 无运行时入口 | 阻断 |
| M2-03「连接模块用降级钩子切回上游」；M2-07 enabled 含 entitlement | 钩子默认 true，无人 `add_filter` | 阻断 |
| M1-10 / M2-05 A1–A9 + `apps.spec.js` | 无文件；services 只有 E4 | 阻断 |
| entitlements.md `expired` 同 exhausted（只读） | REST 只拦 `exhausted` 的写 | 阻断 |
| 广告拦截铁律：不隐藏核心/更新/Site Health | `should_hide` 遵守；CSS 全局 class 可误伤 | 阻断（规则下发后） |
| A8 / UI「小工具目录暂时不可用」 | `GET /apps` 失败仍 200+`[]`；前端只在 apiFetch 抛错时打琥珀条（`Services.js:559-568`，`Index.php:131-140`） | 偏差 |
| apps §5 `wpcy_apps_unknown_app` = 未知 id | 缺 key 也用该码 | 偏差 |
| init.payload.context 在 `site:read` 时等于 GET context | `AppSandbox` 不传 `context`，`pluginVersion: ''`（`Services.js:467-475`） | 偏差 |
| M2-01b 未知 public 白名单 ignored | `jquery`/`react`/`vuejs` 等并进 `jsdelivr`（`Mappers.php:31-45`） | 偏差 |
| README：迁移时校验字体目录，不在目录的 ignored | `new Mappers()` 空目录 = 全放行（`Mappers.php:356-358`） | 偏差 |
| Index 文档：plugin_version 用 `CHINA_YES_VERSION` | 写死 `'4.0.0'`（`Index.php:91-106`）；插件头 Version 仍是 `3.9.3` | 偏差 |
| rest-api 公开挑战 409；entitlements.md 写过 404 | 实现 409（`SiteBindingModule.php:410-417`） | 以 rest-api 为准，可接受 |
| token 存 option vs transient（M0 自选） | transient `wpcy_binding_challenge_{id}` | 可接受，需在报告写明 |
| 默认不打生产 license-server | `api_base()` 空则不出站 | 符合总计划 §0 |
| security.md iframe `no-referrer` | 实现 `strict-origin`（任务书/ADR 要求） | 以任务书为准 |
| M1-10 根依赖 `use-memo-one` | webpack alias，有 revert commit | 有意偏离 |
| M2-07 连接页字体 DataViews | Toggle 禁用且保存时丢掉 | 未做 |

## G 清单

### 阻断

1. **`SiteBindingModule::confirm()` 无运行时入口** — `src/Services/SiteBinding/SiteBindingModule.php:210`；`src/Rest/RestModule.php:199-240`；`src/Admin/app/pages/Services.js:608-626`。修法：`start()` 成功后立刻（或 pending 期间 cron）调 `confirm()`；失败保持 pending 再试。不要新增 rest-api.md 没有的 WP REST。没有这一步，绑定、权益、小工具整条都走不通。

2. **`Degrade` 未接到 `wpcy_entitlement_allows`** — `src/Core/Plugin.php:117-124`；`src/Integrations/Windfonts/WindfontsModule.php:151-160`。修法：`EntitlementsModule::register()` 里 `add_filter( 'wpcy_entitlement_allows', … )`，对 `windfonts` / `admincdn` 等：`shouldUseUpstream($service)===false` 才允许。补测：`Plugin::create()` 后 exhausted fixture 下 `do_action('wp_head')` 不含 windfonts link。

3. **A1–A9 e2e 未交** — 缺 `tests/e2e/apps.spec.js`；`tests/e2e/services.spec.js:5-20`。修法：按 M1-10 表补 9 个 `test()`。A4 要把 `manifest.entry_url` 指到本地 `tests/fixtures/mock-app/index.html`。A8 必须 mock「索引不可达」而不是空数组（需先改 REST，见下）。

4. **NoticeControl CSS 可藏核心通知** — `src/Admin/NoticeControl/NoticeControlModule.php:362-380` vs `:263-267`。修法：拒绝 WP 通用 class（`notice`、`notice-warning`、`notice-error`、`updated` 等）；隐藏改为按 hook 摘 action / 对单条 notice 先 `is_protected`；`print_hide_styles` 不要在未见过的规则上 `record_hidden`。补测：规则 `class=notice-warning` 不得让 `update-nag` 从 CSS 消失。

5. **`expired` 仍可 `data:write`** — `src/Rest/AppsController.php:400-446`。修法：`entitlement_denied` 把 `expired` 与 `exhausted` 同样处理；`canOpenSandbox`：`exhausted` 仍挂 iframe（只读由 REST 拦写），Modal 仅无行 / `expired`。

### 建议

- `GET /apps` 区分空目录与不可达，否则 A8 假绿（`AppsController.php:175-184`，`Services.js:559-568`）。
- 缺 key 不要用 `wpcy_apps_unknown_app`（`AppsController.php:241-246`）。
- `Mappers::PUBLIC_ASSET_MAP` 只留任务书五项；`jquery`/`react`/… 记 `unsupported_whitelist`（`Mappers.php:31-45`）。
- `Index` / `Catalog` 远程请求显式 `timeout≤10`、`sslverify=>true`（`Index.php:268`，`Catalog.php:148-154`）。
- `attachBridge` 传入 context 与真实 `CHINA_YES_VERSION`（`Services.js:467-475`）。
- 连接页打开 Windfonts（去掉 `disabled` 和 `delete next.windfonts`），Catalog 接到 `Plugin::create()`（`Connect.js:62-72,224-227`，`Plugin.php:110`）。
- `Stylesheet.php:131-141` 选择器要 escape / 剥标签。
- `revoke()` 清权益 transient；`refresh()` 在 `status!=='bound'` 时不要出站。
- `composer.json` 把 `process-timeout` 提到 ≥600，否则 `composer check` 的 `@test:legacy` 会在 300s 被杀（本次即如此）。
- 4.0 PHPUnit 纳入 PHP 7.4 job，或 phpstan 设 `phpVersion: 7.4`。
- `AdminModuleTest` 禁词扫 `build/` 或只扫用户文案，不要逼 `'entitl'+'ement'`。
- `RestModule.php:26-28` 注释改掉。

### 确认无误

- v4 不加载 `framework/`；e2e `kernel-no-framework.spec.js` 在文件列表中。
- 凭据密封、`autoload=no`、GET `/binding` / CLI export 不含 credential。
- 默认不打 `license.wenpai.net` / `apps.wpcy.com`。
- iframe 无 `allow-same-origin`；工具拿不到 REST nonce。
- 公开挑战仅 pending 且未过期；过期 `wpcy_binding_not_pending`。
- DELETE `/binding` 只做本地 `revoked`（服务端路径 M0 待定）。
- GET `/entitlements` 不可达无缓存返回 `[]`，不 5xx。
- 迁移不删 `wp_china_yes`；回滚不写回 3.x 结构；`admincdn_public` 三键规则与关闭项一致。
- 公告默认空源、24h 缓存、未知 dismiss 仍 200、请求源不是 weixiaoduo。
- Windfonts 无 `crossorigin`，只挂 `wp_head`。
- 恢复模式站点级、`wp_safe_redirect`、`add_submenu_page` 第一参 `null`。
- config export 多站点三段，import 丢 `effective`。
- Binding/Entitlements HTTP `timeout=10`、`sslverify=true`。
- CI 仍有 PHP 7.4 矩阵；发布 ZIP 校验含 `build/`、排除 `src/Admin/app/`。
- phpstan level 6 本次 `[OK] No errors`；unit 全绿；`composer test:legacy` 单独跑通过。
