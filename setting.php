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
        '将你的WordPress接入本土生态体系中，这将为你提供一个更贴近中国人使用习惯的WordPress',
        '',
        'wpcy'
    );

    add_settings_field(
        'wpcy_field_select_wpapi',
        '选择应用市场',
        'wpcy_field_wpapi_cb',
        'wpcy',
        'wpcy_section_main'
    );

    add_settings_field(
        'wpcy_field_select_super_admin',
        '管理后台加速',
        'wpcy_field_super_admin_cb',
        'wpcy',
        'wpcy_section_main'
    );

    add_settings_field(
        'wpcy_field_select_super_gravatar',
        '加速G家头像',
        'wpcy_field_super_gravatar_cb',
        'wpcy',
        'wpcy_section_main'
    );

    add_settings_field(
        'wpcy_field_select_super_googlefonts',
        '加速谷歌字体',
        'wpcy_field_super_googlefonts_cb',
        'wpcy',
        'wpcy_section_main'
    );
}

add_action('admin_init', 'wpcy_settings_init');

function wpcy_field_wpapi_cb() {
    $wpapi = get_option('wpapi');
    ?>
  <label>
    <input type="radio" value="2" name="wpapi" <?php checked($wpapi, '2'); ?>>官方应用市场加速镜像
  </label>
  <label>
    <input type="radio" value="1" name="wpapi" <?php checked($wpapi, '1'); ?>>本土应用市场（技术试验）
  </label>
  <label>
    <input type="radio" value="3" name="wpapi" <?php checked($wpapi, '3'); ?>>不接管应用市场
  </label>
  <p class="description">
    <b>官方应用市场加速镜像</b>：直接从官方反代并在大陆分发，除了增加对WP-China-Yes插件的更新支持外未做任何更改
  </p>
  <p class="description">
    <b>本土应用市场</b>：与<a href="https://translate.wp-china.org/" target="_blank">本土翻译平台</a>深度整合，为大家提供基于AI翻译+人工辅助校准的全量作品汉化支持（注意，这仍属于试验阶段，存在可能的接口报错、速度缓慢等问题，<a href="https://wp-china.org/forums/forum/228" target="_blank">问题反馈</a>）
  </p>
    <?php
}

function wpcy_field_super_admin_cb() {
    $super_admin = get_option('super_admin');
    ?>
  <label>
    <input type="radio" value="1" name="super_admin" <?php checked($super_admin, '1'); ?>>启用
  </label>
  <label>
    <input type="radio" value="2" name="super_admin" <?php checked($super_admin, '2'); ?>>禁用
  </label>
  <p class="description">
    将WordPress核心所依赖的静态文件切换为公共资源，此选项极大的加快管理后台访问速度
  </p>
    <?php
}

function wpcy_field_super_gravatar_cb() {
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

function wpcy_field_super_googlefonts_cb() {
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
    if (!current_user_can('manage_options')) {
        return;
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
    <a href="https://wp-china.org" target="_blank">WP中国本土化社区</a>的使命是帮助WordPress在中国建立起良好的本土生态环境，以求推进行业整体发展，做大市场蛋糕。<br/>
    特别感谢<a href="https://zmingcx.com/" target="_blank">知更鸟</a>、<a href="https://www.weixiaoduo.com/" target="_blank">薇晓朵团队</a>、<a href="https://www.appnode.com/" target="_blank">AppNode</a>在项目萌芽期给予的帮助。
  </p>
    <?php
}
