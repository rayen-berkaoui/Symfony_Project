<?php

$conn = mysqli_connect('127.0.0.1', 'root', '', 'tabaany');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$result = $conn->query("SHOW INDEXES FROM utilisateur");
$rows = [];
while($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
print_r($rows);

$result2 = $conn->query("SHOW CREATE TABLE utilisateur");
$row2 = $result2->fetch_assoc();
print_r($row2);