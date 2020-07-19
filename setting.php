<?php

function wpcy_settings_init() {
    /**
     * wpapi用以标记用户所选的仓库api，数值说明：1 使用由WP-China.org提供的国区定制API，2 只是经代理加速的api.wordpress.org原版API
     */
    register_setting('wpcy', 'wpapi');

    /**
     * super_admin用以标记用户是否启用管理后台加速功能
     */
    register_setting('wpcy', 'super_admin');

    /**
     * super_gravatar用以标记用户是否启用G家头像加速功能
     */
    register_setting('wpcy', 'super_gravatar');

    /**
     * super_googlefonts用以标记用户是否启用谷歌字体加速功能
     */
    register_setting('wpcy', 'super_googlefonts');

    add_settings_section(
        'wpcy_section_main',
        '这是一个革命性的插件，从此中国人会拥有针对国内环境专门定制的WordPress，以及一个由中国人主导的社区生态环境',
        'wpcy_section_main_cb',
        'wpcy'
    );

    add_settings_field(
        'wpcy_field_select_wpapi',
        '选择仓库源',
        'wpcy_field_wpapi_cb',
        'wpcy',
        'wpcy_section_main',
        []
    );

    add_settings_field(
        'wpcy_field_select_super_admin',
        '加速管理后台',
        'wpcy_field_super_admin_cb',
        'wpcy',
        'wpcy_section_main',
        []
    );

    add_settings_field(
        'wpcy_field_select_super_gravatar',
        '加速G家头像',
        'wpcy_field_super_gravatar_cb',
        'wpcy',
        'wpcy_section_main',
        []
    );

    add_settings_field(
        'wpcy_field_select_super_googlefonts',
        '加速谷歌字体',
        'wpcy_field_super_googlefonts_cb',
        'wpcy',
        'wpcy_section_main',
        []
    );
}

add_action('admin_init', 'wpcy_settings_init');

function wpcy_section_main_cb() {
    ?>
    服务器赞助：<a href="">硅云</a> | <a href="">又拍云</a><br/>
    资金赞助榜：<a href="">赞助榜单</a>
    <?php
}

function wpcy_field_wpapi_cb($args) {
    $wpapi = get_option('wpapi');
    ?>
  <label>
    <input type="radio" value="1" name="wpapi" <?php checked($wpapi, '1'); ?>>中国本土源（推荐）
  </label>
  <label>
    <input type="radio" value="2" name="wpapi" <?php checked($wpapi, '2'); ?>>官方原版源
  </label>
  <p class="description">
    中国本土源由<a href="https://wp-china.org">WP中国本土化社区</a>所开发维护，拥有以下特性：
  <ul style="list-style-type: decimal; margin-left: 30px;">
    <li>直接从国内数据库合成数据并对外提供服务，速度飞快</li>
    <li>对仓库中所有的作品追加基于机器翻译的完全汉化支持，同时支持翻译校准并记忆校准内容，日后推送更新时不会覆盖</li>
    <li>仓库支持中文作品信息显示及中文语义化搜索（开发中）</li>
    <li>支持直接购买国内开发者的优秀作品并享受和官方原版仓库一致的用户体验（开发中）</li>
  </ul>
  </p>
  <p class="description">
    官方原版源直接从api.wordpress.org反代并在大陆分发，除了增加对WP-China-Yes插件的更新支持外未做任何更改
  </p>
    <?php
}

function wpcy_field_super_admin_cb($args) {
    $super_admin = get_option('super_gravatar');
    ?>
  <label>
    <input type="radio" value="1" name="super_admin" <?php checked($super_admin, '1'); ?>>启用
  </label>
  <label>
    <input type="radio" value="2" name="super_admin" <?php checked($super_admin, '2'); ?>>禁用
  </label>
  <p class="description">
    为WordPress核心所依赖的CSS、JS、图片等静态资源进行CDN加速，对于小带宽服务器后台访问速度提升明显。目前支持WordPress 5.4、5.3、5.2、5.1
  </p>
    <?php
}

function wpcy_field_super_gravatar_cb($args) {
    $super_gravatar = get_option('super_gravatar');
    ?>
  <label>
    <input type="radio" value="1" name="super_gravatar" <?php checked($super_gravatar, '1'); ?>>启用
  </label>
  <label>
    <input type="radio" value="2" name="super_gravatar" <?php checked($super_gravatar, '2'); ?>>禁用
  </label>
  <p class="description">
    为Gravatar头像加速，推荐所有用户启用该选项
  </p>
    <?php
}

function wpcy_field_super_googlefonts_cb($args) {
    $super_googlefonts = get_option('super_googlefonts');
    ?>
  <label>
    <input type="radio" value="1" name="super_googlefonts" <?php checked($super_googlefonts, '1'); ?>>启用
  </label>
  <label>
    <input type="radio" value="2" name="super_googlefonts" <?php checked($super_googlefonts, '2'); ?>>禁用
  </label>
  <p class="description">
    请只在主题包含谷歌字体的情况下才启用该选项，以免造成不必要的性能损失
  </p>
    <?php
}

function wpcy_options_page_html() {
    if ( ! current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['settings-updated'])) {
        add_settings_error('wpcy_messages', 'wpcy_message', '保存成功', 'updated');
    }

    settings_errors('wpcy_messages');
    ?>
  <div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
        <?php
        settings_fields('wpcy');
        do_settings_sections('wpcy');
        submit_button('保存配置');
        ?>
    </form>
  </div>
  <p>
    <a href="https://wp-china.org">WP中国本土化社区</a>的使命是帮助WordPress在中国建立起良好的本土生态环境，以求推进行业整体发展，做大市场蛋糕。<br/>
    同时社区也在积极开发<b>备用</b>的WordPress中国衍生版，以防中美意识形态战争摧毁整个国内WordPress相关产业（担忧来源于19年10月至20年5月的429问题）。<br/>
    如果你是开发者或解决方案提供商，请了解<a href="https://wpunion.org.cn">WordPress中国产业联盟</a>。<br/>
    特别感谢<a href="https://zmingcx.com/">知更鸟</a>、<a href="https://www.weixiaoduo.com/">薇晓朵团队</a>、<a href="https://www.appnode.com/">AppNode</a>在项目萌芽期给予的帮助，点击<a href="#">这里</a>了解这段故事。
  </p>
    <?php
}