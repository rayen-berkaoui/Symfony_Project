<?php
$dirs = ["src", "templates"];

$badWords = [
    "?ntityType" => "EntityType",
    "?ntityManagerInterface" => "EntityManagerInterface",
    "?xception" => "Exception",
    "?vent" => "Event",
    "?mail" => "Email",
    "?xcel" => "Excel",
    "?xport" => "Export",
    "?xecute" => "Execute",
    "?mpty" => "Empty",
    "?xtension" => "Extension",
    "?nvironment" => "Environment",
    "?ncode" => "Encode",
    "?rror" => "Error",
    "?xists" => "Exists",
    "?ntity" => "Entity",
    "?lements" => "Elements",
    "?nd" => "End",
    "?cho" => "Echo",
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ["php", "twig"])) {
                $c = file_get_contents($file->getPathname());
                foreach ($badWords as $bad => $good) {
                    $c = str_replace($bad, $good, $c);
                }
                file_put_contents($file->getPathname(), $c);
            }
        }
    }
}
echo "Done.\n";

