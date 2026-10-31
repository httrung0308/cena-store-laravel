<?php

declare(strict_types=1);

$mode = $argv[1] ?? '';
$root = dirname(__DIR__);
$port = getenv('CENA_DB_PORT') ?: '3307';
$host = getenv('CENA_DB_HOST') ?: '127.0.0.1';

if ($mode === '--ci' && in_array(getenv('CI'), ['true', '1'], true)) {
    foreach (['.env', '.env.testing'] as $file) {
        if (file_exists($root.'/'.$file)) {
            throw new RuntimeException('CI requires a clean checkout without existing env files.');
        }
    }
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", 'root', getenv('CENA_MYSQL_ROOT_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('cena_store', 'cena_store_test')")->fetchColumn() > 0
        || (int) $pdo->query("SELECT COUNT(*) FROM mysql.user WHERE User IN ('cena_app', 'cena_ci')")->fetchColumn() > 0) {
        throw new RuntimeException('CI bootstrap refuses an existing Cena schema/account; use a fresh disposable MySQL server.');
    }
    $settings = ['app_password' => bin2hex(random_bytes(24)), 'test_password' => bin2hex(random_bytes(24))];
    foreach (['cena_store', 'cena_store_test'] as $database) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    foreach (['cena_app' => ['cena_store', 'app_password'], 'cena_ci' => ['cena_store_test', 'test_password']] as $user => [$database, $key]) {
        $account = $pdo->quote($user)."@'%'";
        $pdo->exec('CREATE USER IF NOT EXISTS '.$account.' IDENTIFIED BY '.$pdo->quote($settings[$key]));
        $pdo->exec('ALTER USER '.$account.' IDENTIFIED BY '.$pdo->quote($settings[$key]));
        $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON `{$database}`.* TO ".$account);
    }
    $testUser = 'cena_ci';
} elseif ($mode === '--local') {
    $settingsPath = getenv('LOCALAPPDATA').'/CenaStore/services/local-credentials.json';
    $settings = json_decode(file_get_contents($settingsPath), true, flags: JSON_THROW_ON_ERROR);
    if (! ($settings['provisioned'] ?? false)) {
        throw new RuntimeException('Run start-local-services.ps1 before configuring local env.');
    }
    $testUser = 'cena_test';
} else {
    throw new InvalidArgumentException('Use --local or --ci (the latter requires CI=true and a disposable MySQL server).');
}

foreach (['.env' => ['cena_store', 'cena_app', 'app_password'], '.env.testing' => ['cena_store_test', $testUser, 'test_password']] as $file => [$database, $user, $key]) {
    if (file_exists($root.'/'.$file)) {
        if ($mode === '--ci') {
            throw new RuntimeException('CI env already exists; use a clean checkout.');
        }
        echo $file.' already exists; left unchanged.'.PHP_EOL;

        continue;
    }
    $template = file_get_contents($root.'/'.$file.'.example');
    $values = ['DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $database, 'DB_USERNAME' => $user, 'DB_PASSWORD' => $settings[$key], 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32))];
    foreach ($values as $name => $value) {
        $template = preg_replace_callback('/^'.preg_quote($name, '/').'=.*$/m', static fn (): string => $name.'='.$value, $template);
    }
    file_put_contents($root.'/'.$file, $template);
    if (PHP_OS_FAMILY !== 'Windows') {
        chmod($root.'/'.$file, 0600);
    }
    echo 'Created '.$file.' with separate credentials (values not printed).'.PHP_EOL;
}
