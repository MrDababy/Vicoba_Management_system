<?php
echo "<h1>VICOBA Test</h1>";
echo "<p>Server is working!</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

// Check if directories exist
$directories = [
    'app' => '../app',
    'app/controllers' => '../app/controllers',
    'app/views' => '../app/views',
    'config' => '../config',
    'app/views/auth' => '../app/views/auth'
];

foreach ($directories as $name => $path) {
    $fullPath = __DIR__ . '/' . $path;
    echo "<p>" . $name . ": " . (is_dir($fullPath) ? '✅ Exists' : '❌ Missing') . " - " . $fullPath . "</p>";
}

// Check if login.php exists
$loginPath = __DIR__ . '/../app/views/auth/login.php';
echo "<p>Login view: " . (file_exists($loginPath) ? '✅ Exists' : '❌ Missing') . " - " . $loginPath . "</p>";