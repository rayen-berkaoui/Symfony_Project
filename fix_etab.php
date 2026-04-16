<?php
$dirs = ["src", "templates"];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ["php", "twig"])) {
                $c = file_get_contents($file->getPathname());
                
                $c = str_replace("?tablissement", "Etablissement", $c);
                $c = str_replace("??tablissement", "Etablissement", $c);
                $c = str_replace("??ntry", "Entry", $c); // wait, could there be other EE words? Like SLEEP? SL??P?
                $c = preg_replace("/\?\?([a-z])/", "EE$1", $c); // reverse the blind EE
                
                // wait, " ?? " should stay " ?? ".
                
                file_put_contents($file->getPathname(), $c);
            }
        }
    }
}
echo "Done.\n";

