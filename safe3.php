<?php
$f1 = "src/Entity/Etablissement.php";
$c1 = file_get_contents($f1);

// Clean up existing asserts
$c1 = preg_replace('/^[ \t]*#\[Assert\\\\[^\]]+\]\r?\n/m', '', $c1);

$rulesEtab = [
    'nom' => "#[Assert\NotBlank(message: 'Le nom est obligatoire')]\n    #[Assert\Length(min: 3, max: 120)]\n    #[Assert\Regex(pattern: '/^\d+$/', match: false, message: 'Le nom ne doit pas être uniquement numérique')]",
    'description' => "#[Assert\NotBlank(message: 'La description est obligatoire')]\n    #[Assert\Length(min: 10)]",
    'adresse' => "#[Assert\NotBlank(message: 'L\'adresse est obligatoire')]\n    #[Assert\Length(min: 5)]",
    'ville' => "#[Assert\NotBlank(message: 'La ville est obligatoire')]\n    #[Assert\Choice(choices: ['Tunis','Ariana','Ben Arous','Manouba','Nabeul','Zaghouan','Bizerte','Beja','Jendouba','Kef','Siliana','Sousse','Monastir','Mahdia','Sfax','Kairouan','Kasserine','Sidi Bouzid','Gabes','Medenine','Tataouine','Gafsa','Tozeur','Kebili'], message: 'Ville invalide.')]",
    'telephone' => "#[Assert\NotBlank(message: 'Le téléphone est obligatoire')]\n    #[Assert\Regex(pattern: '/^(\+216 )?\d{8}$/', message: 'Le format doit être +216 XXXXXXXX ou XXXXXXXX avec 8 chiffres')]",
    'email' => "#[Assert\NotBlank(message: 'L\'email est obligatoire')]\n    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide')]",
    'horaires' => "#[Assert\NotBlank(message: 'Les horaires sont obligatoires')]\n    #[Assert\Regex(pattern: '/^[A-Za-z]+-[A-Za-z]+ \d{2}:\d{2}-\d{2}:\d{2}$/', message: 'Format lisible ex: Lun-Dim 09:00-22:00')]",
    'gammePrix' => "#[Assert\NotBlank(message: 'La gamme de prix est obligatoire')]\n    #[Assert\Choice(choices: ['€', '€€', '€€€', '€€€€', '€€€€€', '', '', '', '', ''], message: 'Valeur invalide')]",
    'type' => "#[Assert\NotBlank(message: 'Le type est obligatoire')]\n    #[Assert\Choice(choices: ['hotel', 'restaurant', 'cafe', 'museum', 'bar', 'loisir', 'autre'], message: 'Type invalide')]",
    'latitude' => "#[Assert\NotBlank(message: 'La latitude est obligatoire')]\n    #[Assert\Range(min: -90, max: 90)]",
    'longitude' => "#[Assert\NotBlank(message: 'La longitude est obligatoire')]\n    #[Assert\Range(min: -180, max: 180)]"
];

$c1 = preg_replace_callback('/(#\[ORM\\\\Column.*?name: \'([^\']+)\'.*?\n)(\s*private)/', function($m) use ($rulesEtab) {
    if (isset($rulesEtab[$m[2]])) {
        return $m[1] . "    " . $rulesEtab[$m[2]] . "\n" . $m[3];
    }
    return $m[0];
}, $c1);

if (strpos($c1, 'Symfony\Component\Validator\Constraints as Assert') === false) {
    $c1 = str_replace("use Doctrine\ORM\Mapping as ORM;\n", "use Doctrine\ORM\Mapping as ORM;\nuse Symfony\Component\Validator\Constraints as Assert;\n", $c1);
}

file_put_contents($f1, $c1);


$f2 = "src/Entity/Activite.php";
$c2 = file_get_contents($f2);

// Clean up existing asserts
$c2 = preg_replace('/^[ \t]*#\[Assert\\\\[^\]]+\]\r?\n/m', '', $c2);

$rulesAct = [
    'nomActivite' => "#[Assert\NotBlank(message: 'Nom obligatoire')]\n    #[Assert\Length(min: 3)]",
    'description' => "#[Assert\NotBlank(message: 'Description obligatoire')]\n    #[Assert\Length(min: 10)]",
    'categorie' => "#[Assert\NotBlank(message: 'Catégorie obligatoire')]\n    #[Assert\Choice(choices: ['sport', 'culture', 'loisir', 'bien_etre', 'food', 'visite', 'autre'], message: 'Invalide')]",
    'duree' => "#[Assert\NotBlank(message: 'Durée obligatoire')]\n    #[Assert\Positive(message: 'Doit être > 0')]",
    'niveau' => "#[Assert\NotBlank(message: 'Niveau obligatoire')]\n    #[Assert\Choice(choices: ['debutant', 'intermediaire', 'avance'])]",
    'prix' => "#[Assert\NotBlank(message: 'Prix obligatoire')]\n    #[Assert\PositiveOrZero(message: 'Prix >= 0')]",
    'devise' => "#[Assert\NotBlank(message: 'Devise obligatoire')]\n    #[Assert\Length(min: 3, max: 3)]\n    #[Assert\Regex(pattern: '/^[A-Z]+$/')]",
    'date_debut' => "#[Assert\NotBlank(message: 'Date de début obligatoire')]",
    'date_fin' => "#[Assert\NotBlank(message: 'Date de fin obligatoire')]",
    'nb_places' => "#[Assert\NotBlank(message: 'Places obligatoires')]\n    #[Assert\GreaterThanOrEqual(value: 1)]",
    'places_dispo' => "#[Assert\NotBlank(message: 'Places disponibles obligatoires')]\n    #[Assert\GreaterThanOrEqual(value: 0)]",
    'adresse_depart' => "#[Assert\NotBlank(message: 'Adresse obligatoire')]\n    #[Assert\Length(min: 5)]",
    'age_min' => "#[Assert\NotBlank(message: 'Age min obligatoire')]\n    #[Assert\Range(min: 0, max: 120)]",
    'equipement_inclus' => "#[Assert\NotBlank(message: 'Equipement inclus obligatoire')]",
    'conditions_annulation' => "#[Assert\NotBlank(message: 'Conditions annulation obligatoires')]",
    'statut' => "#[Assert\NotBlank(message: 'Statut obligatoire')]\n    #[Assert\Choice(choices: ['disponible', 'complete', 'annulee'])]"
];

$c2 = preg_replace_callback('/(#\[ORM\\\\Column.*?name: \'([^\']+)\'.*?\n)(\s*private)/', function($m) use ($rulesAct) {
    if (isset($rulesAct[$m[2]])) {
        return $m[1] . "    " . $rulesAct[$m[2]] . "\n" . $m[3];
    }
    return $m[0];
}, $c2);

// FK Etablissement
$c2 = preg_replace('/(#\[ORM\\\\ManyToOne[^\n]*\n)\s*(#\[ORM\\\\JoinColumn[^\n]*\n)(\s*private \?Etablissement)/', "$1$2    #[Assert\NotBlank(message: 'L\'établissement doit exister')]\n$3", $c2);

// Class-level constraints for Activite
$classAsserts = <<<EOF
#[ORM\Entity]
#[ORM\Table(name: 'activite')]
#[Assert\Expression(
    "this.getDateDebut() < this.getDateFin()",
    message: "La date de fin doit être après la date de début"
)]
#[Assert\Expression(
    "this.getPlacesDispo() <= this.getNbPlaces()",
    message: "Les places disponibles ne peuvent pas dépasser le nombre total de places"
)]
#[Assert\Expression(
    "this.getStatut() != 'complete' or this.getPlacesDispo() == 0",
    message: "Si le statut est complete, les places disponibles doivent être 0"
)]
EOF;
$c2 = preg_replace('/#\[ORM\\\\Entity\]\r?\n#\[ORM\\\\Table\(name: \'activite\'\)\]/', $classAsserts, $c2);

if (strpos($c2, 'Symfony\Component\Validator\Constraints as Assert') === false) {
    $c2 = str_replace("use Doctrine\DBAL\Types\Types;\n", "use Doctrine\DBAL\Types\Types;\nuse Symfony\Component\Validator\Constraints as Assert;\n", $c2);
}

file_put_contents($f2, $c2);

echo "Strict validations applied.";
?>
