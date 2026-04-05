<?php
$c = file_get_contents("templates/activite/new.html.twig");
preg_match_all("/\\{%\s*block\s+([a-zA-Z0-9_]+)\s*%\\}/", $c, $opened);
preg_match_all("/\\{%\s*endblock\s*(?:[a-zA-Z0-9_]+)?\s*%\\}/", $c, $closed);
echo count($opened[1]) . " blocks opened: " . implode(", ", $opened[1]) . "\n";
echo count($closed[0]) . " blocks closed.\n";

