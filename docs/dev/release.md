状态：草案（M0）· 2026-09-03 · 依据 linuxjoy 定稿 §7

# 发版

## semver

从 **4.0.0** 起：

| 变更 | 版本 |
|------|------|
| 只修 bug / 安全 | `4.x.y`（补丁） |
| 功能 | `4.(x+1).0`（小版本） |
| 破坏性变更 | `5.0.0` |

小版本 **按月**（定稿 §7.6）；安全修复随时。每版在 wpcy.com 有 `/changelog` 条目 + `/news` 公告。

3.9.x LTS 继续 `3.9.z`，只修安全，不为 4.0 迁就结构。

## 流程（4.x）

1. `main` 冻结（禁止再合功能）
2. 版本号 **三处同步**：
   - `wp-china-yes.php` 插件头 `Version:`
   - 常量 `CHINA_YES_VERSION`
   - `package.json` 的 `version`（4.0 引入；现状该文件无 version 字段，待 M1）
3. `CHANGELOG.md`「未发布」改为对应版本段
4. tag `v4.x.y`（指向冻结 HEAD）
5. CI 出 ZIP，附 SHA-256；**ZIP 的 SHA 必须对应该 tag 的 HEAD**
6. GitHub Release（附件为该 ZIP）
7. 分发链路（下一节）可见新版本

执行者打 tag / 发 Release 前须任务书明确授权。默认：代理 **不发布**。

## 分发链路

目前同时存在三条，关系 **待核（发 3.9.3 当天核清）**，此处只记录事实，不编「以谁为准」：

| 通道 | 现状（仓内 / 定稿可见） |
|------|------------------------|
| GitHub Release | `readme.md` 要求用户从 Release 下载，不要下源码包 |
| 云桥 `updates.wenpai.net` | 4.0 更新通道目标（定稿 §7.1-7）；3.x 遥测也走此域 |
| `api.wenpai.net/china-yes/version-check` | 3.x 旧更新检查（PucFactory）；**3.9.3 不动**；4.0 不再作为主通道 |

Forgejo 与 GitHub 谁是分发源，同样待核，不在本文发明。核清后一句话写回定稿 §9，再回写本节。

## 门禁

发版当天必须同时成立：

- ZIP SHA-256 = 被测 tag HEAD 构建产物（旧候选 SHA 作废，不得复用）
- CI 全绿（矩阵见 [testing.md](testing.md)）
- Plugin Check 通过（待 M1）
- 迁移矩阵：`3.9.x → 4.0`、`3.9.x → 4.0 → 停用 → 3.9.x`（单站 + 多站点）
- 恢复页 `?page=wpcy-recovery` 可用（无 JS）
- 发布包检查：见 testing.md / 现有 `scripts/build-release.sh`

## 回滚

4.0 **不写** 3.x option `wp_china_yes`（只读迁移）；3.9.x **不读** 4.0 option（`wpcy_settings` 等，定稿 §7.5a-C）。

站点回 3.9.x：

1. 停用 4.0
2. 安装 3.9.x 发行 ZIP（从当时有效的分发通道，待核）
3. 启用 3.9.x —— 仍读原 `wp_china_yes`；4.0 option 留在库里无害
4. 不要手工把 4.0 键写进 `wp_china_yes`

凭据在 `wpcy_site_identity`，3.9.x 不使用。卸载 4.0 时是否删除 4.0 option，按迁移 spec，默认保留至少一个 4.x 小版本。

## 发版公告

- wpcy.com `/changelog` + `/news`
- 3.x 用户看到的升级预览（4.0 价值与删除项）**由产品侧出文案**；插件只展示给定文本，代理不写替代文案（定稿 §7.2 口径：不写「遥测」类用户文案）

## 3.9.x LTS 发版

只修安全。流程同 4.x，差别：

- 基线分支：`release/3.9` 或 `codex/wpcy-3.9.3-stabilization`（待定 M0）
- tag：`v3.9.z`
- 版本三处：插件头、`CHINA_YES_VERSION`（3.9.x 无强制 `package.json` version）
- 更新检查域 **不动**（仍 `api.wenpai.net`）
- 不加改道、不改 `wp_china_yes` 结构、不加 4.0 模块
