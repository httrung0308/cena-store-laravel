<?php

declare(strict_types=1);

$path = $argv[1];
$settings = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
$password = $settings['provisioned'] ? $settings['admin_password'] : '';
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (! $settings['provisioned']) {
    $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($settings['admin_password']));
}
foreach (['cena_store', 'cena_store_test'] as $database) {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
foreach (['cena_app' => ['cena_store', 'app_password'], 'cena_test' => ['cena_store_test', 'test_password']] as $username => [$database, $key]) {
    $account = $pdo->quote($username)."@'127.0.0.1'";
    $pdo->exec('CREATE USER IF NOT EXISTS '.$account.' IDENTIFIED BY '.$pdo->quote($settings[$key]));
    $pdo->exec('ALTER USER '.$account.' IDENTIFIED BY '.$pdo->quote($settings[$key]));
    $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON `{$database}`.* TO ".$account);
}
$settings['provisioned'] = true;
file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Provisioned isolated databases cena_store / cena_store_test with separate scoped users.'.PHP_EOL;
echo 'MySQL '.$pdo->query('SELECT VERSION()')->fetchColumn().PHP_EOL;
