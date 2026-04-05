<?php
$c = file_get_contents("templates/activite/edit.html.twig");
$c2 = file_get_contents("templates/activite/_form.html.twig");
$total = $c . $c2;
echo substr_count($total, "<form") . " start forms, " . substr_count($total, "</form") . " end forms.";

