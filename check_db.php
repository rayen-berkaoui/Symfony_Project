<?php

$conn = new mysqli('127.0.0.1', 'root', '', 'tabaany');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("DESCRIBE utilisateur");

echo "Utilisateur table columns:\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

$conn->close();
