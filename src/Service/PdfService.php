<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    private Dompdf $domPdf;

    public function __construct()
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        
        // Autoriser le chargement des images distantes / via HTTP
        $pdfOptions->set('isRemoteEnabled', true); 
        $pdfOptions->set('isHtml5ParserEnabled', true);

        // Désactiver certains avertissements inutiles
        $pdfOptions->set('logOutputFile', false);

        $this->domPdf = new Dompdf($pdfOptions);
    }

    public function generatePdf(string $html): string
    {
        $this->domPdf->loadHtml($html);
        
        // (Format A4, Portrait)
        $this->domPdf->setPaper('A4', 'portrait');

        // Rend le HTML en PDF
        $this->domPdf->render();

        // Retourne le fichier PDF sous forme de chaîne de caractères
        return $this->domPdf->output();
    }
}
