<?php
$c = file_get_contents("templates/activite/new.html.twig");
echo "form_start: " . substr_count($c, "form_start") . "\n";
echo "form_end: " . substr_count($c, "form_end") . "\n";

