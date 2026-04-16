<?php
$files = ["src/Entity/Etablissement.php", "src/Entity/Activite.php"];
foreach ($files as $f) {
    if (file_exists($f)) {
        $c = file_get_contents($f);
        // Find every #[ORM\Column...] that does NOT end with )] and fix it
        $c = preg_replace_callback("/#\\[ORM\\\\Column(.*?)(?<!\\))\\](\\s+private)/s", function($matches) {
            return "#[ORM\Column" . $matches[1] . ")]" . $matches[2];
        }, $c);
        file_put_contents($f, $c);
    }
}
echo "Done";

