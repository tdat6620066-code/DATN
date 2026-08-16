<?php

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
