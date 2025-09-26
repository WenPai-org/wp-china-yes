<?php

namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

use function WenPai\ChinaYes\get_settings;

class Comments {
    private $settings;

    public function __construct() {
        $this->settings = get_settings();
        
        add_action('wp_enqueue_scripts', [$this, 'force_enqueue_jquery'], 5);
        
        $this->init();
    }

    public function force_enqueue_jquery() {
        if (!is_admin()) {
            wp_enqueue_script('jquery');
        }
    }

    private function init() {
        if (!isset($this->settings['comments_enable']) || !$this->settings['comments_enable']) {
            return;
        }

        $this->init_role_badge();
        $this->init_remove_website();
        $this->init_validation();
        $this->init_herp_derp();
        $this->init_sticky_moderate();
    }

    private function init_role_badge() {
        if (!isset($this->settings['comments_role_badge']) || !$this->settings['comments_role_badge']) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_role_badge_styles']);
        add_filter('get_comment_author', [$this, 'add_role_badge'], 10, 3);
        add_filter('get_comment_author_link', [$this, 'add_role_badge_to_link']);
    }

    private function init_remove_website() {
        if (!isset($this->settings['comments_remove_website']) || !$this->settings['comments_remove_website']) {
            return;
        }

        add_filter('comment_form_default_fields', [$this, 'remove_website_field']);
    }

    private function init_validation() {
        if (!isset($this->settings['comments_validation']) || !$this->settings['comments_validation']) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_validation_scripts']);
        add_action('wp_footer', [$this, 'add_validation_script']);
        add_filter('preprocess_comment', [$this, 'validate_comment_content']);
    }

    private function init_herp_derp() {
        if (!isset($this->settings['comments_herp_derp']) || !$this->settings['comments_herp_derp']) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_herp_derp_scripts']);
        add_action('wp_head', [$this, 'add_herp_derp_styles']);
        add_filter('comment_text', [$this, 'herp_derp_comment_text'], 40);
    }

    private function init_sticky_moderate() {
        if (!isset($this->settings['comments_sticky_moderate']) || !$this->settings['comments_sticky_moderate']) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueue_sticky_moderate_scripts']);
        add_filter('comment_row_actions', [$this, 'add_sticky_moderate_actions'], 10, 2);
    }

    private $user_role = '';

    public function enqueue_role_badge_styles() {
        wp_add_inline_style('wp-block-library', '
            .comment-author-role-badge {
                display: inline-block;
                padding: 3px 6px;
                margin-left: 0.5em;
                margin-right: 0.5em;
                background: #e8e8e8;
                border-radius: 2px;
                color: rgba(0, 0, 0, 0.6);
                font-size: 0.75rem;
                font-weight: normal;
                text-transform: none;
                text-align: left;
                line-height: 1;
                white-space: nowrap;
                vertical-align: middle;
            }
            .comment-author-role-badge--administrator { background: #c1e7f1; }
            .comment-author-role-badge--contributor { background: #c1f1d1; }
            .comment-author-role-badge--author { background: #fdf5c5; }
            .comment-author-role-badge--editor { background: #fdd8c5; }
            .comment-author-role-badge--subscriber { background: #e8e8e8; }
            .wp-block-comment-author-name .comment-author-role-badge {
                display: inline-block;
                margin-left: 0.5em;
                font-size: 0.75rem;
                vertical-align: middle;
            }
        ');
    }

    public function add_role_badge($author, $comment_id, $comment) {
        global $wp_roles;
        
        if ($wp_roles) {
            $reply_user_id = $comment->user_id;
            if ($reply_user_id && $reply_user = new \WP_User($reply_user_id)) {
                if (isset($reply_user->roles[0])) {
                    $user_role = translate_user_role($wp_roles->roles[$reply_user->roles[0]]['name']);
                    $this->user_role = '<div class="comment-author-role-badge comment-author-role-badge--' . $reply_user->roles[0] . '">' . $user_role . '</div>';
                }
            } else {
                $this->user_role = '';
            }
        }
        return $author;
    }

    public function add_role_badge_to_link($author_link) {
        return $author_link . $this->user_role;
    }

    public function remove_website_field($fields) {
        if (isset($fields['url'])) {
            unset($fields['url']);
        }
        return $fields;
    }

    public function enqueue_validation_scripts() {
        if (!is_singular() || !comments_open()) {
            return;
        }

        wp_enqueue_script('jquery');
        
        wp_register_script('wpcy-comments-validation', '', ['jquery'], '1.0.0', true);
        wp_enqueue_script('wpcy-comments-validation');
        wp_add_inline_script('wpcy-comments-validation', '
            jQuery(document).ready(function($) {
                console.log("WP China Yes Comments: Validation script loaded");
                $("#commentform").on("submit", function(e) {
                    var author = $("#author").val();
                    var email = $("#email").val();
                    var comment = $("#comment").val();
                    var errors = [];

                    if ($("#author").length && (!author || author.length < 2)) {
                        errors.push("请输入您的姓名（至少2个字符）");
                    }

                    if ($("#email").length && (!email || !isValidEmail(email))) {
                        errors.push("请输入有效的邮箱地址");
                    }

                    if ($("#comment").length && (!comment || comment.length < 20)) {
                        errors.push("评论内容至少需要20个字符");
                    }

                    if (errors.length > 0) {
                        e.preventDefault();
                        alert("请修正以下错误：\n" + errors.join("\n"));
                        return false;
                    }
                });

                function isValidEmail(email) {
                    var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    return regex.test(email);
                }
            });
        ');
    }

    public function add_validation_script() {
        if (!is_singular() || !comments_open()) {
            return;
        }
        ?>
        <style>
        .comment-form .required {
            color: #d63638;
        }
        .comment-form input:invalid,
        .comment-form textarea:invalid {
            border-color: #d63638;
        }
        </style>
        <?php
    }

    public function validate_comment_content($commentdata) {
        if (strlen($commentdata['comment_content']) < 20) {
            wp_die('评论内容至少需要20个字符。', '评论验证失败', ['back_link' => true]);
        }

        if (str_contains($commentdata['comment_content'], 'href=')) {
            wp_die('评论中不允许包含活动链接，请返回编辑。', '评论验证失败', ['back_link' => true]);
        }

        return $commentdata;
    }

    public function enqueue_herp_derp_scripts() {
        if (!is_singular() || !comments_open()) {
            return;
        }

        wp_enqueue_script('jquery');
        
        wp_register_script('wpcy-comments-herpderp', '', ['jquery'], '1.0.0', true);
        wp_enqueue_script('wpcy-comments-herpderp');
        wp_add_inline_script('wpcy-comments-herpderp', '
            jQuery(document).ready(function($) {
                console.log("WP China Yes Comments: Herp Derp jQuery ready");
                
                function derp(p, herpa) {
                    if (!p.herp) {
                        p.herp = p.innerHTML;
                        var textContent = p.herp.replace(/<[^>]*>/g, "");
                        var derpText = "";
                        var chars = textContent.split("");
                        var inWord = false;
                        
                        for (var i = 0; i < chars.length; i++) {
                            var char = chars[i];
                            
                            if (/[a-zA-Z]/.test(char)) {
                                if (!inWord) {
                                    if (derpText && !/\s$/.test(derpText)) derpText += " ";
                                    derpText += "阿巴";
                                    inWord = true;
                                }
                            } else if (/[\u4e00-\u9fff]/.test(char)) {
                                if (inWord && !/\s$/.test(derpText)) derpText += " ";
                                derpText += Math.random() > 0.5 ? "阿" : "巴";
                                inWord = false;
                            } else if (/\s/.test(char)) {
                                if (inWord) {
                                    inWord = false;
                                }
                                if (derpText && !/\s$/.test(derpText)) {
                                    derpText += " ";
                                }
                            }
                        }
                        
                        p.derp = derpText.trim();
                    }
                    p.innerHTML = (herpa ? p.herp : p.derp);
                }

                function herpa(derpa) {
                    $(".herpc").each(function() {
                        derp(this, !derpa);
                    });
                }

                function initHerpDerp() {
                    var commentsContainer = $("#comments, .comments-area, .comment-list, ol.commentlist, .wp-block-comments");
                    console.log("Comments containers found:", commentsContainer.length);
                    
                    if (commentsContainer.length === 0) {
                        console.log("No comments container found, trying body");
                        var bodyContainer = $("body");
                        if (bodyContainer.length > 0) {
                            var herpDiv = $("<div class=\"herpderp\" style=\"position: fixed; top: 10px; right: 10px; z-index: 9999; background: white; padding: 5px; border: 1px solid #ccc;\"></div>");
                            var checkbox = $("<input type=\"checkbox\" id=\"herp-derp-toggle\">");
                            var label = $("<label for=\"herp-derp-toggle\">阿巴阿巴</label>");
                            
                            herpDiv.append(label).append(checkbox);
                            
                            checkbox.on("change", function() {
                                console.log("Herp derp toggled:", this.checked);
                                herpa(this.checked);
                            });
                            
                            bodyContainer.append(herpDiv);
                        }
                        return;
                    }

                    var targetContainer = commentsContainer.first();
                    console.log("Target container:", targetContainer[0]);
                    
                    var herpDiv = $("<div class=\"herpderp\"></div>");
                    var checkbox = $("<input type=\"checkbox\" id=\"herp-derp-toggle\">");
                    var label = $("<label for=\"herp-derp-toggle\">阿巴阿巴</label>");
                    
                    herpDiv.append(label).append(checkbox);
                    
                    checkbox.on("change", function() {
                        console.log("Herp derp toggled:", this.checked);
                        herpa(this.checked);
                    });
                    
                    targetContainer.before(herpDiv);
                }

                initHerpDerp();
            });
        ');
    }

    public function add_herp_derp_styles() {
        if (!is_singular() || !comments_open()) {
            return;
        }
        ?>
        <style type="text/css">
        .herpderp {
            float: right;
            text-transform: uppercase;
            font-size: 7pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .herpderp input[type="checkbox"] {
            margin-left: 5px;
        }
        </style>
        <?php
    }

    public function herp_derp_comment_text($text) {
        if (!is_singular() || is_feed()) {
            return $text;
        }
        return '<span class="herpc">' . $text . '</span>';
    }

    public function enqueue_sticky_moderate_scripts($hook) {
        if ($hook !== 'edit-comments.php') {
            return;
        }

        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                $(".comment-sticky-moderate").on("click", function(e) {
                    e.preventDefault();
                    var commentId = $(this).data("comment-id");
                    var action = $(this).hasClass("sticky") ? "unsticky" : "sticky";
                    
                    $.post(ajaxurl, {
                        action: "sticky_moderate_comment",
                        comment_id: commentId,
                        sticky_action: action,
                        nonce: "' . wp_create_nonce('sticky_moderate_nonce') . '"
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    });
                });
            });
        ');

        add_action('wp_ajax_sticky_moderate_comment', [$this, 'handle_sticky_moderate_ajax']);
    }

    public function add_sticky_moderate_actions($actions, $comment) {
        if ($comment->comment_approved == '0') {
            $is_sticky = get_comment_meta($comment->comment_ID, '_sticky_moderate', true);
            $text = $is_sticky ? '取消置顶' : '置顶审核';
            $class = $is_sticky ? 'sticky' : '';
            
            $actions['sticky_moderate'] = sprintf(
                '<a href="#" class="comment-sticky-moderate %s" data-comment-id="%d">%s</a>',
                $class,
                $comment->comment_ID,
                $text
            );
        }
        return $actions;
    }

    public function handle_sticky_moderate_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'sticky_moderate_nonce')) {
            wp_die('安全验证失败');
        }

        $comment_id = intval($_POST['comment_id']);
        $action = sanitize_text_field($_POST['sticky_action']);

        if ($action === 'sticky') {
            update_comment_meta($comment_id, '_sticky_moderate', 1);
        } else {
            delete_comment_meta($comment_id, '_sticky_moderate');
        }

        wp_send_json_success();
    }
}