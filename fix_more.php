<?php
$dirs = ["src", "templates"];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ["php", "twig"])) {
                $c = file_get_contents($file->getPathname());
                
                $c = str_replace("?E ", "?? ", $c);
                $c = str_replace("E->", "?->", $c);
                $c = str_replace(" EE", " ??", $c);
                $c = str_replace("EE ", "?? ", $c);
                $c = str_replace("EE", "??", $c); // might be risky if we have a real EE
                
                file_put_contents($file->getPathname(), $c);
            }
        }
    }
}
echo "Done.\n";

