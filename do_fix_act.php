<?php
$f = 'src/Entity/Activite.php';
$c = file_get_contents($f);
if (strpos($c, 'Symfony\Component\Validator\Constraints as Assert') === false) {
    $c = str_replace("use Doctrine\DBAL\Types\Types;\n", "use Doctrine\DBAL\Types\Types;\nuse Symfony\Component\Validator\Constraints as Assert;\n", $c);
    $c = str_replace("use Doctrine\DBAL\Types\Types;\r\n", "use Doctrine\DBAL\Types\Types;\nuse Symfony\Component\Validator\Constraints as Assert;\n", $c);
}

// nomActivite
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'nomActivite\'.*?\n)\s*private \?string \$nomActivite = null;/s', "$1    #[Assert\NotBlank(message: 'Le nom de l\'activité est obligatoire')]\n    #[Assert\Length(min: 3, max: 120, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères')]\n    private ?string \$nomActivite = null;", $c);

// description
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'description\'.*?\n)\s*private \?string \$description = null;/s', "$1    #[Assert\NotBlank(message: 'La description est obligatoire')]\n    #[Assert\Length(min: 10, minMessage: 'La description doit comporter au moins {{ limit }} caractères')]\n    private ?string \$description = null;", $c);

// duree
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'duree\'.*?\n)\s*private \?int \$duree = null;/s', "$1    #[Assert\Positive(message: 'La durée doit être supérieure à zéro')]\n    private ?int \$duree = null;", $c);

// prix
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'prix\'.*?\n)\s*private \?string \$prix = null;/s', "$1    #[Assert\NotBlank(message: 'Le prix est obligatoire')]\n    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif')]\n    private ?string \$prix = null;", $c);

// nb_places
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'nb_places\'.*?\n)\s*private \?int \$nb_places = null;/s', "$1    #[Assert\NotBlank(message: 'Le nombre de places est obligatoire')]\n    #[Assert\Positive(message: 'Le nombre de places doit être supérieur à zéro')]\n    private ?int \$nb_places = null;", $c);

// date_fin
$c = preg_replace('/(#\[ORM\\\\Column\(name: \'date_fin\'.*?\n)\s*private \?\\\\DateTimeInterface \$date_fin = null;/s', "$1    #[Assert\Expression(expression: 'this.getDateDebut() == null or this.getDateFin() == null or this.getDateDebut() <= this.getDateFin()', message: 'La date de fin ne peut pas être antérieure à la date de début')]\n    private ?\DateTimeInterface \$date_fin = null;", $c);

// etablissement (fk)
$c = preg_replace('/(#[^\n]*\n)\s*(#[^\n]*\n)\s*private \?Etablissement \$etablissement = null;/s', "$1$2    #[Assert\NotNull(message: 'Veuillez sélectionner un établissement')]\n    private ?Etablissement \$etablissement = null;", $c);


file_put_contents($f, $c);
echo "Done Activite\n";
