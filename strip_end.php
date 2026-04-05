<?php
function removeLastEndblock($file) {
    if (file_exists($file)) {
        $c = file_get_contents($file);
        $pos = strrpos($c, "{% endblock %}");
        if ($pos !== false) {
            $c = substr_replace($c, "", $pos, strlen("{% endblock %}"));
            // Clean up trailing whitespace
            $c = rtrim($c) . "\n";
            file_put_contents($file, $c);
            echo "Stripped last endblock from $file\n";
        }
    }
}
removeLastEndblock("templates/activite/new.html.twig");
removeLastEndblock("templates/activite/edit.html.twig");

