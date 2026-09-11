# M-UI-1c-r2 最终报告（Grok）

基线：`8fc94ba`。分支：`grok/m-ui-1c`。评审：`docs/dev-plan/reports/M-UI-1c-review-linuxjoy.md`（main，缺口 1–3）。

## 修正项

- [x] 1. 头像合并（schema 级）
- [x] 2. CO-14 后台加速整体作废
- [x] 3. MotuCloud 第 5 核心服务

## 1. 改了哪些文件

见下方 `git diff --stat`。未改 `docs/specs/*` / `docs/design/admin-ui-spec.md`（规格滞后由统筹补，本轮只改实现）。未入库 `.grok-context/`。

## 2. 对照

| 缺口 | 实现位置 | 状态 |
|------|----------|------|
| 单一 `connectivity.avatar` | `src/Config/Schema.php`、`Defaults.php`、`Profile.php` | 已做。枚举 `cravatar_cn` / `cravatar_global` / `off`，默认 `cravatar_cn` |
| 三场景默认全站 cravatar_cn | `Profile.php` domestic / crossborder / inbound / mixed | 已做。跨境不再「前台默认关」 |
| 3.x 映射 + weavatar 说明 | `src/Migration/Mappers.php` | 已做。`cravatar=cn/global/weavatar/off` → 单值；weavatar 仍记 ignored |
| 旧 `{admin,frontend}` / `avatar_admin` REST 键 | `DocumentWriter.php`、`SchemaMigrator.php` | 已做。PUT 对象压成单值；GET/bootstrap 不再吐 `avatar_admin` / `avatar_frontend` |
| Connect 单一「头像」 | `src/Admin/app/pages/Connect.js` | 已做。无后台/前台拆分 |
| Overview 去掉「后台/前台头像」 | `Overview.js` inbound 统计卡改「头像请求」 | 已做 |
| Connect 去掉「后台加速」 | `Connect.js` 删除 CO-14 字段 | 已做 |
| Overview 混合站文案 | `Overview.js`「后台资源走国内可达源」 | 已做 |
| 迁移报告 | `Report.php`「后台静态加速已取消（易致后台界面问题）」 | 已做。计入 ignored_entries，notes=`admin_assets_dropped` |
| MotuCloud 第 5 行 | `svcStatus.js` `motuRow`，图标 `image`（RiImageLine），头像之后、中文字体之前 | 已做。国内默认「已接通」 |
| 锚点分母 5 | `Overview.js` `total = 5` | 已做。截图：国内 4/5、跨境 3/5、降级 3/5（琥珀） |
| 词表「4 项核心服务」 | `Eco.js` 署名栏补「图标与图片」 | 已做 |

## 3. 验收命令与输出

### `rg -n "后台加速|admin_assets|avatar_admin|avatar_frontend" src/`

仅剩迁移映射与升级路径（无 UI 字段、无 REST 兄弟键）：

```
src/Migration/Mappers.php:236:if ( $mapped['admin_assets'] ) {
src/Migration/Mappers.php:354: * @return array{assets: array<int, string>, unknown: array<int, string>, admin_assets: bool}
src/Migration/Mappers.php:366:$admin_assets = false;
src/Migration/Mappers.php:369:$admin_assets = true;
src/Migration/Mappers.php:389:'admin_assets' => $admin_assets,
src/Migration/Report.php:140:if ( $this->admin_assets_dropped() ) {
src/Migration/Report.php:141:$document['notes']    = array( 'admin_assets_dropped' );
src/Migration/Report.php:153:public function admin_assets_dropped(): bool {
src/Config/SchemaMigrator.php:27: * … Dropped `admin_assets` is ignored.
src/Config/SchemaMigrator.php:63: * Collapse split avatar / drop retired admin_assets …
src/Config/SchemaMigrator.php:74:unset( $document['admin_assets'] );
src/Config/Repository.php:477:if ( array_key_exists( 'admin_assets', $raw ) ) {
```

`avatar_admin` / `avatar_frontend` /「后台加速」在 `src/` 为零。`Repository.php:477` 只在读旧 option 时丢掉死键并回写。

### `composer check`

exit 0。PHPStan `[OK] No errors`（101/101）。PHPUnit 全套绿（含 config 83、rest 33、migration 45）。

### `npm run lint:js`

exit 0（仅 ESLint v10 eslintrc 既有警告）。

### `npm run build`

exit 0。`webpack 5.110.3 compiled with 2 warnings`（entrypoint size / runtimeChunk，既有）。

### 截图

`docs/design/screens/m-ui-1c/`：

- `overview-国内正常.png` — 锚点 **4/5**，矩阵含「图标与图片 · MotuCloud · 已接通」，5 枚状态点
- `overview-跨境正常.png` — 锚点 **3/5**
- `overview-降级.png` — 锚点 **3/5**（琥珀）
- `connect-头像字段.png` — 高级模式单一「头像」字段，无后台/前台拆分，无「后台加速」

命令：`BASE_URL=http://localhost:8890 WP_USERNAME=admin WP_PASSWORD=wpcy-preview npx playwright test --config=tests/visual/playwright.config.js --grep 'overview-domestic|overview-crossborder|overview-degraded|connect-avatar'` → 4 passed。

## 4. 没做 / 做不到 / 有疑问

- 评审缺口 4–7（e2e Studio 拷贝、1024 截图、locale 钩子、服务端推荐清单、帮助 URL）按统筹处置进 M-UI-1d / 后端，本轮未做。
- MotuCloud 第 5 行目前无独立诊断分组，矩阵固定「已接通」（对照原型 domestic/crossborder）。后端探测未在本轮范围。
- 仓内 `docs/specs/config-schema.md` / `admin-ui-spec.md` 仍写拆分 avatar 与 CO-14；规格以 main 决定文档为准，本轮不改规格仓。
- e2e 本机未跑（Studio 拷贝限制，同缺口 4）。
- 预览站 `~/Studio/wpcy-40-preview/.../wp-china-yes` 不是软链；截图前把 `build/` 资产拷进该站。插件源码未同步。

## Definition of Done

- [x] 缺口 1–3 对照表
- [x] 截图：概览国内/跨境/降级 + Connect 头像
- [x] 用户可见串：无「后台加速」「即将提供」「4.1 起生效」；无「后台头像/前台头像」字段
- [x] `composer check` / `npm run build` / `lint:js` 绿
- [x] `rg` 清零（Migration + 升级路径除外）
- [x] 报告含没做
