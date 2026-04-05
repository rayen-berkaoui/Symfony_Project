<?php
$f = 'src/Entity/Etablissement.php';
$c = file_get_contents($f);
if (strpos($c, 'Symfony\Component\Validator\Constraints as Assert') === false) {
    $c = str_replace("use Doctrine\ORM\Mapping as ORM;\r\n", "use Doctrine\ORM\Mapping as ORM;\nuse Symfony\Component\Validator\Constraints as Assert;\n", $c);
    $c = str_replace("use Doctrine\ORM\Mapping as ORM;\n", "use Doctrine\ORM\Mapping as ORM;\nuse Symfony\Component\Validator\Constraints as Assert;\n", $c);
}

// nom
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'nom\'.*?\n)\s*private \?string \$nom = null;/s', "$1    #[Assert\NotBlank(message: 'Le nom est obligatoire')]\n    #[Assert\Length(min: 3, max: 120, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères', maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]\n    private ?string \$nom = null;", $c);

// description
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'description\'.*?\n)\s*private \?string \$description = null;/s', "$1    #[Assert\NotBlank(message: 'La description est obligatoire')]\n    #[Assert\Length(min: 10, minMessage: 'La description doit comporter au moins {{ limit }} caractères')]\n    private ?string \$description = null;", $c);

// adresse
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'adresse\'.*?\n)\s*private \?string \$adresse = null;/s', "$1    #[Assert\NotBlank(message: 'L\'adresse est obligatoire')]\n    #[Assert\Length(min: 5, max: 180, minMessage: 'L\'adresse doit faire au moins {{ limit }} caractères')]\n    private ?string \$adresse = null;", $c);

// ville
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'ville\'.*?\n)\s*private \?string \$ville = null;/s', "$1    #[Assert\NotBlank(message: 'La ville est obligatoire')]\n    private ?string \$ville = null;", $c);

// telephone
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'telephone\'.*?\n)\s*private \?string \$telephone = null;/s', "$1    #[Assert\Regex(pattern: '/^[0-9\+\s\-]+$/', message: 'Le numéro de téléphone n\'est pas valide')]\n    private ?string \$telephone = null;", $c);

// email
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'email\'.*?\n)\s*private \?string \$email = null;/s', "$1    #[Assert\Email(message: 'L\'adresse email {{ value }} n\'est pas valide.')]\n    private ?string \$email = null;", $c);

// type
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'type\'.*?\n)\s*private \?string \$type = \'autre\';/s', "$1    #[Assert\Choice(choices: ['hotel', 'restaurant', 'cafe', 'museum', 'bar', 'loisir', 'autre'], message: 'Type invalide')]\n    private ?string \$type = 'autre';", $c);

file_put_contents($f, $c);
echo "Done Etablissement\n";
