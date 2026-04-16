<?php
$files = ["templates/activite/edit.html.twig", "templates/activite/new.html.twig"];
foreach ($files as $f) {
    if (file_exists($f)) {
        $c = file_get_contents($f);
        // Completely strip all {% endblock %} tags at the end of the file
        $c = preg_replace("/(?:\\{%\s*endblock\s*%\\}\\s*)+$/is", "", $c);
        
        // Ensure there is exactly ONE endblock at the end of the file.
        $c = rtrim($c) . "\n{% endblock %}\n";
        file_put_contents($f, $c);
    }
}
echo "Cleaned endblocks.";

