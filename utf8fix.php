<?php
function cleanDir($dir) {
    if (!is_dir($dir)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ["php", "twig", "yaml", "yml"])) {
            $content = file_get_contents($file->getPathname());
            
            // Revert all mangled characters
            $fixed = utf8_decode($content);
            if(strpos($fixed, "Ã") !== false) {
                 $fixed = utf8_decode($fixed);
            }
            if(strpos($content, "Ã") !== false || strpos($content, "Ôƒ") !== false) {
                 file_put_contents($file->getPathname(), $fixed);
                 echo "Cleaned specific strings: " . $file->getPathname() . PHP_EOL;
            }
        }
    }
}
cleanDir(__DIR__ . "/templates");
cleanDir(__DIR__ . "/src");
