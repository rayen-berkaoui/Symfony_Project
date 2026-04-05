<?php
$c = file_get_contents("src/Entity/Activite.php");
$c = preg_replace("/\\]\\)\\]\\s*\\n/u", "]\n", $c);
file_put_contents("src/Entity/Activite.php", $c);

