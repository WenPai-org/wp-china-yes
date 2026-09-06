# ADR-005：外部插件功能的模块化并入规范

状态：已定 2026-09-06  
日期：2026-09-06  
来源：[`docs/dev-plan/decisions/2026-09-06-http-block-merge-and-feature-absorption.md`](../dev-plan/decisions/2026-09-06-http-block-merge-and-feature-absorption.md)（feibisi / linuxjoy 定稿；B 节。A 节 HTTP Block 三层模型是本规范的第一份实例，规格见 [`data-residency-ruleset.md`](../specs/data-residency-ruleset.md) / [`config-schema.md`](../specs/config-schema.md) / [`rest-api.md`](../specs/rest-api.md)）

## 解决什么问题

文派过往开发过的独立插件（第一份是未发布的 HTTP Block）还要并进叶子 4.0。若每次并入都把原插件目录搬进来、另起 REST 命名空间、或在模块里写死「是不是国内站」，会把 4.0 模块合同冲掉，也会把已删的「用户任意加域名」一类能力用别的名字加回来。

本 ADR 把决定 B 节写成架构合同：并入的是能力、不是代码；一个能力一个模块；只经四个插槽接入；场景与作用域横切；有删除路径。后续并入按固定四步走，不再为每个旧插件单开维护仓。

## 背景

- HTTP Block（内部插件，从未发布）挂在 `pre_http_request` 上，用户可加任意 pattern（正则 / 通配 / 子串），与 4.0 已删的 3.x「飞行模式」同类。分析见 [`docs/dev-plan/verification/http-block-analysis-2026-09-06.md`](../dev-plan/verification/http-block-analysis-2026-09-06.md)。产品拍板：不迁源码、不新设 `HttpBlock` 品牌；L0 受保护主机并进驻留 Ruleset；L2 为极窄的 `Privacy/SiteBlocklist`（只拦、exact/suffix、上限 20）。
- feibisi：后续还会并入多个过往插件，不再单独维护。名单待补（决定 B3）。
- 现有模块合同：[`docs/dev/module-authoring.md`](../dev/module-authoring.md)（`Module` / `ConditionalModule`，`register()` 只挂钩子、`enabled()` 读 Config）。

## 决定

### 原则（七条，照抄决定 B1，不得改）

1. **并入的是能力，不是代码。** 先做只读分析（功能清单 / 缺陷 / 与 4.0 重叠 / 落点），统筹拍板后按 4.0 模块合同重写；原插件源码不进仓。
2. **一个能力 = 一个模块**，遵守 `docs/dev/module-authoring.md` 合同（`Module` / `ConditionalModule`，`register()` 只挂钩子、`enabled()` 读 Config），可独立开关、可独立删除。
3. **模块只通过四个「插槽」接入产品**，不得直写别的模块：配置（`Schema` 命名空间 `modules.<name>`）、REST（`wpcy/v1/<name>/*`，同一命名空间下的子路径，不另起命名空间）、设置界面（向「简单/高级」两档各注册若干行，由 M-UI 定义的注册接口）、诊断（向诊断页注册一张只读卡或一段记录）。需要时再加：迁移映射（向 `Migration\Mappers` 注册旧 option 键 → 新键）、服务端规则（向签名 ruleset 增加键，不新增规则类型）。
4. **场景与作用域是横切关注点**：模块声明每个开关在三种场景下的默认值与允许的作用域；不在模块内自行判断「是不是国内站」。
5. **商业露出统一走服务端规则**：模块不带自己的推广位、不写死「了解 →」链接；需要露出的服务登记到服务端下发的服务目录。
6. **用户可见字符串**登记 [`admin-ui-spec.md`](../design/admin-ui-spec.md) §4 词表并编号；错误文案按 [`copy-guidelines.md`](../dev/copy-guidelines.md)。
7. **删除路径**：每个并入模块的任务书必须写「如何整体移除」（配置键、REST、cron、option 清理），保证以后能再拆出去。

接口形状、PHP 数组形态、SiteBlocklist 并入示例见 [`module-authoring.md`](../dev/module-authoring.md)「四个插槽」「场景与作用域声明」「删除路径」。

### 并入流程（固定四步，每步一个 Grok 任务 + 统筹审）

1. **分析**（只读）：按 HTTP-BLOCK 任务的提纲（功能清单 / 架构质量 / 与 4.0 重叠映射 / 约束冲突 / 免费高级切分 / 工作量）。
2. **决定**：统筹写决定文件（与本决定同格式）。
3. **规格**：ADR（若原则有增量）+ `config-schema.md` / `rest-api.md` / 词表增量 + 任务书（含 DoD）。
4. **实现**：引擎任务（CI 回路 + 独立审查）；UI 部分进设计门禁随 M-UI 排期。

不得跳步：没有决定文件不得写规格；没有规格不得写引擎；UI 无 [`design-sop.md`](../dev/design-sop.md) 认可不得拆 M-UI 任务书。

### 四个插槽（B1-3 原文展开）

| 插槽 | 合同 | 禁止 |
|------|------|------|
| 配置 | `modules.<name>`（点分 id 与 `Module::id()` 同一路径）进 [`config-schema.md`](../specs/config-schema.md)；读写只经 `Config\Repository` | 直写 `get_option` / 自创 option 键；在 schema 里加「配额」字段（连通性与并入的免费能力都不卖功能） |
| REST | `wpcy/v1/<name>/*` 或既有资源下的子路径（如 `/residency/protected`）；错误码前缀 `wpcy_` | 另起 REST 命名空间（如 `/http-block/v1`） |
| 设置界面 | 向简单 / 高级两档各注册行。接口名先定为 `SettingsRows::register( string $module, array $simple_rows, array $advanced_rows )`，**待 M-UI 实现** | 模块自己 `add_menu_page` / 直写 `src/Admin/app/` |
| 诊断 | 向诊断页注册一张只读卡或一段记录 | 独立请求日志表、完整 URL、来源回溯 |

可选增量（仍不是第五个插槽）：

- 迁移：向 `Migration\Mappers` 注册旧键 → 新键。无旧品、从未发布 → 任务书写「迁移无」。
- 服务端规则：向现有 Ed25519 ruleset **加键**，不新增规则类型、不另起 kid 体系。

### 删除路径

并入模块的引擎任务书必须列出：

- 配置键（`modules.<name>` 及子键）从 Schema / Defaults / 默认矩阵拿掉
- REST 路由从 `RestModule` 拿掉
- cron hook 与 transient / 自定义 option / 自定义表
- 词表行与诊断注册
- 测试与 fixtures

卸掉后其它模块不得再引用该 id。不允许用「隐藏 section」代替删除（ADR-001）。

### 第一份实例（HTTP Block → 三层，不是本 ADR 的原则变更）

决定 A 节（不得在本 ADR 改值）：

- L0 受保护主机：签名 `protected_hosts` + 客户端硬编码兜底；永远放行；用户规则命中无效；多站点不允许站点覆盖。
- L1 数据驻留表：现有 A/B/C；用户不可编辑；不可当拦截器。
- L2 本站拦截清单：`Privacy/SiteBlocklist`；只能 block；exact / suffix；上限 20；网络级。
- 优先级：L0 放行 → L1 驻留 → L2 拦截 → 默认放行；后层不得推翻前层。
- 不设高级档。噪声拦截包走签名下发，用户整包开关。
- 不迁移 HTTP Block 源码；不给用户正则/任意域名入口；不新起品牌名。

规格增量与任务书：M-ABSORB-0 交付的 specs + [`M-BLOCK-1.md`](../dev-plan/tasks/M-BLOCK-1.md) / [`M-BLOCK-UI.md`](../dev-plan/tasks/M-BLOCK-UI.md)。

## 约束

- 决定本身不许改。异议进任务报告「有疑问」，不在 ADR / 规格里改原则或 A 节表。
- 原插件源码不进仓。
- 不在模块内写死场景判断或推广链接。
- 不写用户可见「遥测」「匿名数据」「隐私开关」。
- 第三方商业域名一律 `https://wpcy.com/go/…`。
- 不为并入的免费能力建 `Services/Entitlements` 配额。
- 不把已删功能用隐藏 section 留着。L2 是已拍板的极窄例外（只拦、20 条、不能改道），不是飞行模式复活。

## 备选方案与放弃理由

### 直接搬代码为子目录

把旧插件（如 `http-block/`）整树放进 `src/Integrations/` 或 `src/Privacy/HttpBlock/`，外面包一层 `Module`。

放弃。并入的是能力不是代码（B1-1）。HTTP Block 的 contains 误伤、每个出站请求写 performance option、独立 REST 命名空间、内置 100+ 黑名单与 4.0 镜像对撞，搬进来等于把缺陷和品牌一起搬进来。4.0 模块合同要求按 Schema / 插槽重写。

### 做成独立插件依赖叶子

叶子提供钩子，HTTP Block（或其它旧插件）作为独立插件检测叶子并挂钩。

放弃。产品明确「不再单独维护」。两套设置页、两套 REST、两套更新通道；用户会装错、会把文派主机拦掉。叶子要的是站点接入端里的一项能力，不是插件市场里的配套件。

### 插件市场式动态加载

签名下载功能包，运行时 `include` 进叶子进程（类小工具容器，但执行 PHP）。

放弃。小工具容器（ADR-003）加载的是跨域 HTML，`sandbox` 且无 `allow-same-origin`。动态 PHP 等于远程代码执行面，与「模块进仓、随版本发布、可独立删除」相反。服务端规则只下发数据（ruleset 加键），不下来代码。

## 后果

正面：

- 后续并入有固定四步和四个插槽，执行者不必再猜落点。
- 每个并入模块可独立开关、可整块拆走。
- HTTP Block 的能力以 L0/L2/噪声包进入 4.0，不带原插件源码和品牌。

代价：

- 旧插件的 UI、日志表、导入导出、仪表盘小工具一律不移植，分析阶段就要写进「不做什么」。
- 设置行注册接口待 M-UI，引擎任务不得改 `src/Admin/app/`。
- L2 与定稿「用户不可自行加域名」并置：靠上限 20、只拦、保护主机拒保存 + 运行时忽略来收窄，而不是靠付费墙。

## 验收

- 本文含 B1 七条、B2 四步、四个插槽、删除路径；备选方案含「直接搬代码为子目录」「独立插件依赖叶子」「插件市场式动态加载」并写放弃理由。
- [`module-authoring.md`](../dev/module-authoring.md) 含四个插槽、场景与作用域声明、删除路径、SiteBlocklist 并入示例。
- HTTP Block A 节字段进 [`config-schema.md`](../specs/config-schema.md)、[`rest-api.md`](../specs/rest-api.md)、[`data-residency-ruleset.md`](../specs/data-residency-ruleset.md)；词表进 [`admin-ui-spec.md`](../design/admin-ui-spec.md) §4。
- 引擎任务书 [`M-BLOCK-1.md`](../dev-plan/tasks/M-BLOCK-1.md)；UI 需求 [`M-BLOCK-UI.md`](../dev-plan/tasks/M-BLOCK-UI.md)。

## 不做什么

- 不迁移 HTTP Block 源码。
- 不给用户正则 / 通配 / 路径规则。
- 不设付费墙、不新起 `HttpBlock` 品牌。
- 不在模块内写死场景判断或推广链接。
- 不改决定文件原文。
