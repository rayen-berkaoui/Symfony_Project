<?php
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("templates/"));
$found = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() == "twig") {
        $content = file_get_contents($file->getPathname());
        // Find strings longer than 3 characters containing non-ascii
        if (preg_match_all("/[a-zA-Z]*[^\x00-\x7F]+[a-zA-Z]*/u", $content, $matches)) {
            $found[$file->getPathname()] = array_unique($matches[0]);
        }
    }
}
print_r($found);

