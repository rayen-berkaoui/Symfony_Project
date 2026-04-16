<?php
$dirs = ["src", "templates"];

$replacements = [
    "\u{00E9}" => "e", // é
    "\u{00E8}" => "e", // è
    "\u{00EA}" => "e", // ê
    "\u{00EB}" => "e", // ë
    "\u{00E0}" => "a", // à
    "\u{00E2}" => "a", // â
    "\u{00E4}" => "a", // ä
    "\u{00EF}" => "i", // ï
    "\u{00EE}" => "i", // î
    "\u{00F4}" => "o", // ô
    "\u{00F6}" => "o", // ö
    "\u{00F9}" => "u", // ù
    "\u{00FB}" => "u", // û
    "\u{00FC}" => "u", // ü
    "\u{00E7}" => "c", // ç
    "\u{00C9}" => "E", // É
    "\u{00C8}" => "E", // È
    "\u{00CA}" => "E", // Ê
    "\u{00CB}" => "E", // Ë
    "\u{00C0}" => "A", // À
    "\u{00C2}" => "A", // Â
    "\u{00C4}" => "A", // Ä
    "\u{00CF}" => "I", // Ï
    "\u{00CE}" => "I", // Î
    "\u{00D4}" => "O", // Ô
    "\u{00D6}" => "O", // Ö
    "\u{00D9}" => "U", // Ù
    "\u{00DB}" => "U", // Û
    "\u{00DC}" => "U", // Ü
    "\u{00C7}" => "C", // Ç
    "\u{0529}" => "e", //  (mojibake for é)
    "\u{00E3}" => "a", // ã
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ["php", "twig"])) {
                $c = file_get_contents($file->getPathname());
                $orig = $c;
                foreach ($replacements as $accent => $normal) {
                    $c = str_replace($accent, $normal, $c);
                }
                
                // Extra moji-bake specific to ISO-8859-1 mismatch (e.g., "CatÃgories" -> "Categories")
                $c = str_replace("Ã", "e", $c);
                $c = str_replace("Ã¨", "e", $c);
                $c = str_replace("Ã*", "a", $c);
                
                if ($orig !== $c) {
                    file_put_contents($file->getPathname(), $c);
                }
            }
        }
    }
}
echo "Accents removed.\n";

