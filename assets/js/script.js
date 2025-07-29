    jQuery(document).ready(function($) {
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
