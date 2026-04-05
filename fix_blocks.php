<?php
$files = [
    "templates/etablissement/edit.html.twig",
    "templates/etablissement/new.html.twig",
    "templates/activite/edit.html.twig",
    "templates/activite/new.html.twig"
];

$sweetAlertSnippet = "
    {% if form is defined and form.vars.submitted and not form.vars.valid %}        
    <script src=\"https://cdn.jsdelivr.net/npm/sweetalert2@11\"></script>
    <script>
        document.addEventListener(\"DOMContentLoaded\", function() {
            Swal.fire({
                icon: \"error\",
                title: \"Données invalides\",
                text: \"Veuillez corriger les erreurs indiquées dans le formulaire et réessayer.\",
                confirmButtonColor: \"#f5c518\",
                background: \"#161616\",
                color: \"#f3efe7\"
            });
        });
    </script>
    {% endif %}
";

foreach ($files as $f) {
    if (file_exists($f)) {
        $c = file_get_contents($f);
        // Remove everything from the first "form is defined" block onwards to the EOF
        $pos = strpos($c, "{% if form is defined and form.vars.submitted and not form.vars.valid %}");
        if ($pos !== false) {
            $c = substr($c, 0, $pos);
        }
        
        // Remove any trailing whitespace
        $c = rtrim($c);
        
        // Ensure "}} \n{% endblock %}" exists and add our script right before it
        $c = str_replace("{% endblock %}", $sweetAlertSnippet . "\n{% endblock %}", $c);
        
        // Notice: str_replace might replace multiple {% endblock %} tags depending on how the file is structured. 
        // We only want to place it in the last block (usually javascripts or body).
        // Let's do a more careful replacement.
    }
}

