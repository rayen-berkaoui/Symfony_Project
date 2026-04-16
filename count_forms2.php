<?php
$c = file_get_contents("templates/activite/edit.html.twig");
echo "form_start: " . substr_count($c, "form_start") . "\n";
echo "form_end: " . substr_count($c, "form_end") . "\n";
echo "delete form included: " . substr_count($c, "delete_form") . "\n";

