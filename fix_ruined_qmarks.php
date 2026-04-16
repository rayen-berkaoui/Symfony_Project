<?php
$dirs = ["src", "templates"];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ["php", "twig"])) {
                $c = file_get_contents($file->getPathname());
                
                // PHP tags
                $c = str_replace("<Ephp", "<?php", $c);
                $c = str_replace("<E=", "<?=", $c);
                $c = str_replace("E>", "?>", $c);
                
                // Nullable types
                $c = preg_replace("/(:\\s*)E([a-zA-Z\\\\]+)/", "$1?$2", $c);
                $c = preg_replace("/(private|protected|public|static|var)\\s+E([a-zA-Z\\\\]+)/", "$1 ?$2", $c);
                $c = preg_replace("/\\(\\s*E([a-zA-Z\\\\]+)/", "(?$1", $c);
                $c = preg_replace("/,\\s*E([a-zA-Z\\\\]+)/", ", ?$1", $c);
                
                // Type hinting (e.g. `function(Estring $a)`) is tricky, let's catch the most common ones
                $c = preg_replace("/(?<=[\\s\\(\\,])E(?=(int|string|float|bool|array|object|callable|iterable|mixed|self|static|parent|\\\\?[a-zA-Z0-9_\\\\]+)\\s+\\$)/", "?", $c);
                
                // Operators
                $c = str_replace(" EE ", " ?? ", $c);
                $c = str_replace("E:", "?:", $c);
                $c = preg_replace("/\\s+E\\s+/", " ? ", $c); // ternary
                
                // Twig specific
                // If there are ternary operators in twig: `foo E bar : baz`
                
                file_put_contents($file->getPathname(), $c);
            }
        }
    }
}
echo "Recovered ? tokens.\n";

