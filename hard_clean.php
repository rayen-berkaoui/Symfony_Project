<?php
$files = ["templates/activite/edit.html.twig", "templates/activite/new.html.twig"];
foreach($files as $f) {
    if (file_exists($f)) {
        $c = file_get_contents($f);
        // Strip everything after the final endif
        $pos = strrpos($c, "{% endif %}");
        if ($pos !== false) {
            $c = substr($c, 0, $pos + 11);
        }
        $c = $c . "\n{% endblock %}\n";
        file_put_contents($f, $c);
    }
}

