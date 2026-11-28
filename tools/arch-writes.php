<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__);
$vendorRoot = is_file($packageRoot . '/vendor/autoload.php') ? $packageRoot : dirname($packageRoot, 2);
$phpstan = $vendorRoot . '/vendor/bin/phpstan';
$config = $packageRoot . '/tools/package-phpstan.neon';
$rule = $packageRoot . '/tools/Architecture/PackageOwnedWriteRule.php';

if (! is_file($phpstan)) {
    fwrite(STDERR, "Install package or monorepo dev dependencies first.\n");
    exit(2);
}

$runtimeConfig = tempnam(sys_get_temp_dir(), 'references-phpstan-');

if ($runtimeConfig === false) {
    fwrite(STDERR, "Unable to create a temporary PHPStan configuration.\n");
    exit(2);
}

$runtimeConfig .= '.neon';
@unlink(mb_substr($runtimeConfig, 0, -5));
$quotedConfig = str_replace("'", "''", $config);
$quotedConfigDirectory = str_replace("'", "''", $packageRoot . '/config');
$contents = "includes:\n    - '{$quotedConfig}'\nparameters:\n    configDirectories:\n        - '{$quotedConfigDirectory}'\n";

if (file_put_contents($runtimeConfig, $contents) === false) {
    @unlink($runtimeConfig);
    fwrite(STDERR, "Unable to write a temporary PHPStan configuration.\n");
    exit(2);
}

$command = sprintf(
    '%s %s analyse -c %s --autoload-file=%s --no-progress',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($phpstan),
    escapeshellarg($runtimeConfig),
    escapeshellarg($rule),
);

$previous = getcwd();

if ($previous === false) {
    @unlink($runtimeConfig);
    fwrite(STDERR, "Unable to determine the current working directory.\n");
    exit(2);
}

chdir($vendorRoot);
passthru($command, $exitCode);
chdir($previous);
@unlink($runtimeConfig);

exit($exitCode);
