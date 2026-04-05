<?php
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("templates/"));
$found = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() == "twig") {
        $content = file_get_contents($file->getPathname());
        if (preg_match_all("/Cat[^\s]gories/u", $content, $matches)) {
            $found[$file->getPathname()] = array_unique($matches[0]);
        }
    }
}
print_r($found);

