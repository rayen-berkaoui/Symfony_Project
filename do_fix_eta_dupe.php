<?php
$f = 'src/Entity/Etablissement.php';
$c = file_get_contents($f);
$c = preg_replace('/use Symfony\\\\Component\\\\Validator\\\\Constraints as Assert;([\r\n]+)use Symfony\\\\Component\\\\Validator\\\\Constraints as Assert;/', "use Symfony\Component\Validator\Constraints as Assert;", $c);
file_put_contents($f, $c);
