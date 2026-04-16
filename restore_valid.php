<?php
$c = file_get_contents("src/Controller/EtablissementController.php");
$c = str_replace(
    "if (\$form->isSubmitted()) {",
    "if (\$form->isSubmitted() && \$form->isValid()) {",
    $c
);
file_put_contents("src/Controller/EtablissementController.php", $c);

$c = file_get_contents("src/Controller/ActiviteController.php");
$c = str_replace(
    "if (\$form->isSubmitted()) {",
    "if (\$form->isSubmitted() && \$form->isValid()) {",
    $c
);
file_put_contents("src/Controller/ActiviteController.php", $c);
echo "Restored isValid();";

