<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (getenv('APP_ENV') !== 'local') {
    fwrite(STDERR, "Database reset is only allowed when APP_ENV=local.\n");
    exit(1);
}

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbName = 'smashzone';

try {
    $mysqli = new mysqli($host, $user, $pass);
    
    if ($mysqli->connect_error) {
        die('Connection failed: ' . $mysqli->connect_error);
    }
    
    // Drop database
    if ($mysqli->query("DROP DATABASE IF EXISTS $dbName")) {
        echo "Database dropped successfully.\n";
    }
    
    // Create database
    if ($mysqli->query("CREATE DATABASE $dbName")) {
        echo "Database created successfully.\n";
    }
    
    $mysqli->close();
    echo "Database reset complete.\n";
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
