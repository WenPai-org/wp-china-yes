// templates/maintenance-default.php
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($title); ?></title>
    <style>
        /* 添加你的样式 */
    </style>
</head>
<body>
    <div class="maintenance-container">
        <h1><?php echo esc_html($heading); ?></h1>
        <div class="message">
            <?php echo wp_kses_post($message); ?>
        </div>
    </div>
</body>
</html>
