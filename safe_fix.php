<?php
function cleanDir($dir) {
    if (!is_dir($dir)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ["php", "twig", "yaml", "yml"])) {
            $content = file_get_contents($file->getPathname());
            
            // Revert the mangled strings directly
            $fixed = strtr($content, [
                 "DÔƒÂcouvrez" => "Découvrez",
                 "activitÔƒÂs" => "activités",
                 "PrÃƒÂªt" => "Prêt",
                 "PrÔƒÂªt" => "Prêt",
                 "GÃƒÂrez" => "Gérez",
                 "GÔƒÂrez" => "Gérez",
                 "rÃƒÂservations" => "réservations",
                 "rÔƒÂservations" => "réservations",
                 "mÃªme" => "même",
                 "mÔƒÂªme" => "même",
                 "DÃcouvrez" => "Découvrez",
                 "activitÃs" => "activités",
                 "prÃfÃrÃs" => "préférés",
                 "prÔƒÂfÔƒÂrÔƒÂs" => "préférés",
                 "Ã" => "é",
                 "Ã" => "à",
                 "àª" => "ê", // because Ãª might be aª
                 "Ã¨" => "è",
                 "Ã" => "î",
                 "Ã" => "ô",
                 "Ã§" => "ç",
                 "Â" => "",
                 "Â" => ""
            ]);

            if ($content !== $fixed) {
                file_put_contents($file->getPathname(), $fixed);
                echo "Cleaned specific strings: " . $file->getPathname() . PHP_EOL;
            }
        }
    }
}
cleanDir(__DIR__ . "/templates");
cleanDir(__DIR__ . "/src");

