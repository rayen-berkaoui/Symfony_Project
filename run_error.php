<?php
$c = file_get_contents("src/Controller/EtablissementController.php");
$c = str_replace(
    "if (\$form->isSubmitted() && \$form->isValid()) {",
    "if (\$form->isSubmitted()) {",
    $c
);
file_put_contents("src/Controller/EtablissementController.php", $c);

$c = file_get_contents("src/Controller/ActiviteController.php");
$c = str_replace(
    "if (\$form->isSubmitted() && \$form->isValid()) {",
    "if (\$form->isSubmitted()) {",
    $c
);
file_put_contents("src/Controller/ActiviteController.php", $c);

