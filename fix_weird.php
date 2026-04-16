<?php
$dirs = ["templates", "src"];
$badChar = urldecode("%D4%A9"); // 
$badChar2 = urldecode("%C3%83%C2%A9"); // Ã
$badChar3 = urldecode("%C3%83%C2%A8"); // Ã¨
$badChar4 = urldecode("%C3%AF%C2%BF%C2%BD"); // ï
$replacements = [
    $badChar => "e",
    $badChar2 => "e",
    $badChar3 => "e",
    $badChar4 => "e",
    "ï" => "e",
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
echo "Done.\n";

