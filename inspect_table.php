<?php

require __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

$params = [
    'dbname' => 'tabaany',
    'user' => 'root',
    'password' => '',
    'host' => '127.0.0.1',
    'driver' => 'pdo_mysql',
];

try {
    $conn = DriverManager::getConnection($params);
    
    echo "Connected to database successfully!\n\n";
    
    // Get table structure
    $sql = "DESCRIBE utilisateur";
    $stmt = $conn->prepare($sql);
    $result = $stmt->executeQuery();
    
    echo "Utilisateur table structure:\n";
    echo str_repeat("=", 80) . "\n";
    printf("%-20s %-20s %-10s %-10s %-10s %-20s\n", "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("=", 80) . "\n";
    
    while ($row = $result->fetchAssociative()) {
        printf("%-20s %-20s %-10s %-10s %-10s %-20s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'], 
            $row['Default'] ?? 'NULL', 
            $row['Extra']
        );
    }
    
    echo str_repeat("=", 80) . "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
