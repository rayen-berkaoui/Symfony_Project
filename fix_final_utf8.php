<?php
$dirs = ["templates", "src"];
$replacements = [
    "?" => "e", "?¨" => "e", "?il" => "oeil",
    urldecode("%E9%BF%BD") => "e", // ? represents a corrupted e
    "?" => "e", 
    "?" => "a", 
    "?" => "E",
    "" => "e",
    "Ã" => "e",
    "Ã¨" => "e"
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ["php", "twig"])) {
                $content = file_get_contents($file->getPathname());
                $newContent = str_replace(array_keys($replacements), array_values($replacements), $content);
                if ($content !== $newContent) {
                    file_put_contents($file->getPathname(), $newContent);
                    echo "Cleaned " . $file->getPathname() . "\n";
                }
            }
        }
    }
}
echo "Done final utf8 fixes.\n";

