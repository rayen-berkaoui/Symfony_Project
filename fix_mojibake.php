<?php
$dirs = ["src", "templates"];

$regexReplacements = [
    "/Cat\S+gorie/i" => "Categorie",
    "/cat\S+gorie/i" => "categorie",
    "/s\S+lectionner/i" => "selectionner",
    "/d\S+tails/i" => "details",
    "/cr\S+er/i" => "creer",
    "/Cr\S+er/i" => "Creer",
    "/E\S+tablissement/i" => "Etablissement",
    "/e\S+tablissement/i" => "etablissement",
    "/Modifi\S+/i" => "Modifier",
    "/modifi\S+/i" => "modifier",
    "/supprim\S+/i" => "supprimer",
    "/Supprim\S+/i" => "Supprimer",
    "/num\S+ro/i" => "numero",
    "/Num\S+ro/i" => "Numero",
    "/t\S+l\S+phone/i" => "telephone",
    "/T\S+l\S+phone/i" => "Telephone",
    "/g\S+n\S+ral/i" => "general",
    "/r\S+f\S+rence/i" => "reference",
    "/pr\S+c\S+dent/i" => "precedent",
    "/Pr\S+f\S+rences/i" => "Preferences",
    "/param\S+tres/i" => "parametres",
    "/v\S+rifi\S+/i" => "verifie",
    "/r\S+ussie/i" => "reussie",
    "/R\S+ussie/i" => "Reussie",
    "/D\S+connexion/i" => "Deconnexion",
    "/d\S+connexion/i" => "deconnexion",
    "/Veuillez/i" => "Veuillez", // Not accented but ok
    "/[\x{FFFD}\x{00E9}\x{00E8}\x{00EA}\x{00EB}E]/u" => "e", // blindly replace replacement char and some others with e
    "/[\x{00C9}\x{00C8}\x{00CA}\x{00CB}]/u" => "E",
    "/[\x{00E0}\x{00E2}\x{00E4}]/u" => "a",
    "/[\x{00EE}\x{00EF}]/u" => "i",
    "/[\x{00F4}\x{00F6}]/u" => "o",
    "/[\x{00F9}\x{00FB}\x{00FC}]/u" => "u",
    "/[\x{00E7}]/u" => "c",
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ["php", "twig"])) {
                $c = file_get_contents($file->getPathname());
                $orig = $c;
                
                // Extra regex to fix words containing the replacement block
                foreach ($regexReplacements as $regex => $repl) {
                    $c = preg_replace($regex, $repl, $c);
                }
                
                // Specific common fixes
                $c = preg_replace("/[A-Za-z]*[A-Za-z]*/", "e", $c);
                
                if ($orig !== $c) {
                    file_put_contents($file->getPathname(), $c);
                }
            }
        }
    }
}
echo "Mojibake targeted.\n";

