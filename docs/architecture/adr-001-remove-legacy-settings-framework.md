# ADR-001：WP-China-Yes 4.0 删除旧设置框架

状态：提案  
日期：2026-08-13

## 背景

3.x 通过 `framework/` 下的私有设置框架和 `Service/Setting.php` 生成管理页面。现有实现同时包含全局类、Composer files 自动加载、字段组件、压缩 JavaScript/CSS、设置 schema、营销页面和产品导航。

已经确认的问题：

- 大量全局类不符合当前 PSR-4 映射，构建持续产生警告；
- 设置项和功能实现没有一一对应，存在只有开关/外链、没有 Service 的条目；
- 模块显示由 `enabled_sections` 决定，而管理该字段的页面本身可能不可见；
- 当前 WordPress 升级会不断暴露旧 jQuery API 和字段组件兼容问题；
- 单个大型 option 混合本地功能、界面、品牌、云服务和历史字段；
- 框架代码体积和测试面远大于 4.0 实际需要的四个管理页面。

## 决策

WP-China-Yes 4.0 不加载、不复制、不渐进封装旧 `framework/`。

管理界面采用：

1. 管理界面是一个整站 React 应用（见 ADR-002），外加一个服务端渲染的恢复页；不再使用旧框架，也不复制任何通用设置框架。
2. REST API `wpcy/v1` 服务 React 应用与小工具桥接。
3. Site Health 输出连接和环境诊断；
4. WP-CLI 提供可自动化的状态、诊断、配置和刷新命令。

## 约束

- 新代码不得 `require` 或 Composer autoload `framework/`；
- 新设置不得写入 `wp_china_yes`；
- 新字段必须在 `Config/Schema` 定义，界面只消费 schema，不自创业务默认值；
- 无 JavaScript 时，恢复页仍可关闭全部改写与模块；
- React 应用加载失败时，恢复页入口必须仍可达（菜单项由 PHP 注册）；
- 管理页面只使用 WordPress 样式语义，不重新造通用表单组件库；
- 所有写操作必须检查 capability、nonce、schema 和 scope；
- 删除功能不能以“隐藏 section”代替，必须从注册、设置、迁移和宣传中删除；
- 管理页面任何位置不出现“遥测”“匿名数据”“隐私开关”字样；报告行为不是设置项。

## 迁移办法

旧框架不是一次提交直接删除，而是按依赖断开：

1. 新建 `src/`、新 bootstrap 路径和新 option；
2. 新内核实现免费连接能力；
3. React 后台应用（经 `wpcy/v1` REST）与恢复页接管新 option；
4. 迁移器只读 3.x option 并输出 dry-run；
5. 真实升级和回滚矩阵通过；
6. 确认生产代码无旧 framework 引用；
7. 删除 `framework/`、`Service/Setting.php`、旧模板和退役 Service；
8. 发布包门禁禁止再次包含上述路径。

## 不采用的方案

### 继续升级旧框架

否决。需要长期维护字段库、jQuery 兼容、全局类和自定义资源，成本与插件核心价值无关。

### 给旧框架外面包一层 Adapter

否决。只隐藏耦合，不消除 Composer files、副作用加载、大 option 和字段实现债务。

### 整个后台全部改成 React SPA

**2026-09-03 推翻**：因小工具容器、权益配额、公告使四页中三页为动态内容，且产品方倾向站点编辑器风格原生界面，改为整站 React；保留恢复页作为无 JS 逃生口。原否决理由（构建、无障碍、REST 权限面）转为 ADR-002 的约束。

### 引入另一个第三方设置框架

否决。只是替换依赖来源，没有解决 schema、作用域、迁移和业务边界问题。

## 后果

正面：

- 删除大量无关代码和兼容面；
- 设置与业务模块有明确 schema；
- 后台故障不会阻断前台连接能力；
- WordPress 核心升级的适配成本降低；
- 更适合对 CDN 服务进行权限和状态建模。

代价：

- 4.0 不能直接复用全部 3.x 字段；
- 必须编写迁移器和升级说明；
- 后台 React 应用需要维护一套 `@wordpress/scripts` 构建链与 e2e 测试（见 ADR-002）；
- 3.9.x 需要单独维持有限 LTS 期间。

## 验收

- 生产 ZIP 不含 `framework/`；
- 首方代码搜索不到 `WP_CHINA_YES::createSection` 和旧全局字段类；
- 恢复页在禁用 JavaScript 时可关闭全部改写；
- React 应用加载失败时，恢复页入口仍可达；
- Plugin Check、PHPCS、PHPStan、WordPress 矩阵和浏览器测试通过；
- 3.x 升级 dry-run、执行和回滚均有固定 fixtures。

## 修订记录

2026-09-03 与 linuxjoy 定稿对齐。

2026-09-03（晚）按定稿 §7.5a-D 修订：决策 1、2 合并为整站 React 应用 + 服务端渲染恢复页；REST `wpcy/v1` 服务 React 与小工具桥接；无 JS 约束仅对恢复页成立；原「否决全 SPA」推翻，理由转入 ADR-002。
