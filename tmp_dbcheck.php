<?php

$db = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
$r = $db->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='ecommerc_test'")->fetchAll();
if (count($r) === 0) {
    $db->exec('CREATE DATABASE ecommerc_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo 'CREATED_DB' . PHP_EOL;
} else {
    echo 'DB_EXISTS' . PHP_EOL;
}