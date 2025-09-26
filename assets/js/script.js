jQuery(document).ready(function($) {
        // 移动端菜单切换功能
        function initMobileMenu() {
            // 添加菜单按钮
            if (!$('.wp_china_yes-mobile-menu-btn').length) {
                $('.wp_china_yes-header-inner').prepend(
                    '<button class="wp_china_yes-mobile-menu-btn" style="display: none; position: absolute; left: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 18px; cursor: pointer; z-index: 10000;">☰</button>'
                );
            }
            
            // 检查屏幕尺寸
            function checkScreenSize() {
                if ($(window).width() <= 782) {
                    $('.wp_china_yes-mobile-menu-btn').show();
                    $('.wp_china_yes-nav-normal').removeClass('mobile-open');
                } else {
                    $('.wp_china_yes-mobile-menu-btn').hide();
                    $('.wp_china_yes-nav-normal').removeClass('mobile-open');
                }
            }
            
            // 菜单按钮点击事件
            $(document).on('click', '.wp_china_yes-mobile-menu-btn', function() {
                $('.wp_china_yes-nav-normal').toggleClass('mobile-open');
            });
            
            // 点击内容区域关闭菜单
            $(document).on('click', '.wp_china_yes-content', function() {
                if ($(window).width() <= 782) {
                    $('.wp_china_yes-nav-normal').removeClass('mobile-open');
                }
            });
            
            // 窗口大小改变时检查
            $(window).resize(checkScreenSize);
            checkScreenSize();
        }
        
        initMobileMenu();
        
        $("#test-rss-feed").click(function() {
            var button = $(this);
            var result = $("#rss-test-result");
            var feedUrl = $("#custom_rss_url").val();
            
            if (!feedUrl) {
                result.html('<span style="color: red;">请先填写 RSS 源地址</span>');
                return;
            }
            
            button.prop("disabled", true);
            result.html("测试中...");
            
            $.post(ajaxurl, {
                action: "test_rss_feed",
                _ajax_nonce: '<?php echo wp_create_nonce("wp_china_yes_nonce"); ?>',
                feed_url: feedUrl
            })
            .done(function(response) {
                result.html(response.success ? 
                    '<span style="color: green;">' + response.data + '</span>' : 
                    '<span style="color: red;">' + response.data + '</span>'
                );
            })
            .fail(function() {
                result.html('<span style="color: red;">测试失败</span>');
            })
            .always(function() {
                button.prop("disabled", false);
            });
        });
    });
