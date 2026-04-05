<?php

$host = '127.0.0.1';
$db = 'tabaany';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("DESCRIBE utilisateur");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/plain');
    echo "Utilisateur Table Structure:\n";
    echo str_repeat("=", 100) . "\n";
    
    foreach ($columns as $col) {
        echo sprintf("%-25s %-30s %-10s %-10s %-15s %-20s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'], 
            $col['Key'], 
            $col['Default'] ?? 'NULL', 
            $col['Extra']
        );
    }
    
    echo str_repeat("=", 100) . "\n";
    
    // Also output as JSON for easy parsing
    echo "\n\nJSON Format:\n";
    echo json_encode($columns, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
