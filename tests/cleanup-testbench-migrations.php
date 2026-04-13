<?php

declare(strict_types=1);

/**
 * Orchestra Testbench's `package:discover` can copy this package's migrations and config
 * into testbench-core's Laravel app. That causes duplicate migrations and stale config
 * (missing new keys like `models.group`). Remove those published copies.
 */
$base = dirname(__DIR__).'/vendor/orchestra/testbench-core/laravel';

$migrationDir = $base.'/database/migrations';

if (is_dir($migrationDir)) {
    foreach (glob($migrationDir.'/*.php') ?: [] as $file) {
        $content = @file_get_contents($file);

        if ($content === false) {
            continue;
        }

        if (str_contains($content, 'messaging_table(')) {
            unlink($file);
        }
    }
}

$publishedConfig = $base.'/config/messaging.php';

if (is_file($publishedConfig)) {
    unlink($publishedConfig);
}
