<?php

$root = dirname(__DIR__);
$failures = [];

function check_runtime($condition, $message) {
    global $failures;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL {$message}\n");
        return;
    }
    echo "PASS {$message}\n";
}

$maintenance = file_get_contents($root . '/Service/Maintenance.php');
check_runtime(
    false !== strpos($maintenance, 'function check_maintenance_mode') &&
    false !== strpos($maintenance, 'function add_admin_bar_notice'),
    'maintenance hooks point to implemented callbacks'
);

$memory = file_get_contents($root . '/Service/Memory.php');
check_runtime(
    false !== strpos($memory, '$this->initialize();') &&
    false === strpos($memory, "add_action('plugins_loaded', [\$this, 'initialize'])"),
    'memory feature registers after the plugin services are constructed'
);

$fonts = file_get_contents($root . '/Service/Fonts.php');
$settings = file_get_contents($root . '/Service/Setting.php');
check_runtime(
    false === strpos($fonts, 'crossorigin="anonymous"') &&
    false !== strpos($fonts, "['en', 'zh', 'zh-common', 'full']") &&
    false !== strpos($settings, "'wenfeng-hcszt'"),
    'Windfonts output matches the current CSS API and avoids forced CORS'
);

$first_party = '';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (
        'php' !== strtolower($file->getExtension()) ||
        $path === __FILE__ ||
        false !== strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) ||
        false !== strpos($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)
    ) {
        continue;
    }
    $first_party .= file_get_contents($path);
}
check_runtime(
    !preg_match('/\b(?:str_contains|str_starts_with|str_ends_with)\s*\(/', $first_party),
    'first-party runtime does not call PHP 8-only string helpers'
);
check_runtime(
    false === strpos($first_party, 'utf8_decode('),
    'first-party runtime avoids deprecated utf8_decode'
);

$comments = file_get_contents($root . '/Service/Comments.php');
$init_position = strpos($comments, "add_action('wp_ajax_sticky_moderate_comment'");
$enqueue_position = strpos($comments, 'public function enqueue_sticky_moderate_scripts');
check_runtime(
    false !== $init_position && false !== $enqueue_position && $init_position < $enqueue_position,
    'comment moderation AJAX handler registers before the separate AJAX request'
);

$framework_js = file_get_contents($root . '/framework/assets/js/main.min.js')
    . file_get_contents($root . '/framework/assets/js/plugins.min.js');
check_runtime(
    false === strpos($framework_js, '.keydown(') &&
    !preg_match('/(?:^|[^A-Za-z])(?:S|r|i)\.isArray\(/', $framework_js),
    'settings UI avoids jQuery APIs deprecated by current WordPress'
);

if ($failures) {
    exit(1);
}
