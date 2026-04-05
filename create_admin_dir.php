<?php
$dir = __DIR__ . '/templates/admin';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    echo "Directory created: $dir\n";
} else {
    echo "Directory already exists: $dir\n";
}
