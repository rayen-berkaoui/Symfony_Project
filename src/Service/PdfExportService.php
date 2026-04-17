<?php

declare(strict_types=1);

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Génère des PDF HTML→PDF (Dompdf) avec en-tête / pied de page Tabaani Connect.
 */
final class PdfExportService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $projectDir,
        private readonly string $siteName,
        private readonly string $siteTagline,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function buildPdfResponse(string $twigTemplate, array $context, string $downloadBaseName): Response
    {
        $context['pdf_site_name'] = $this->siteName;
        $context['pdf_site_tagline'] = $this->siteTagline;
        $context['pdf_generated_at'] = new \DateTimeImmutable();

        $html = $this->twig->render($twigTemplate, $context);

        $publicDir = $this->projectDir.\DIRECTORY_SEPARATOR.'public';
        $options = new Options();
        $options->setChroot([$publicDir, $this->projectDir]);
        $options->setDefaultPaperSize('a4');
        $options->setDefaultPaperOrientation('portrait');
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        if (method_exists($canvas, 'page_text')) {
            $w = $canvas->get_width();
            $footer = $this->siteName.' — p. {PAGE_NUM} / {PAGE_COUNT}';
            $font = 'Helvetica';
            $size = 8.0;
            $color = [0.35, 0.35, 0.42];
            $probe = str_replace(['{PAGE_NUM}', '{PAGE_COUNT}'], ['99', '99'], $footer);
            $textWidth = $canvas->get_text_width($probe, $font, $size);
            $x = max(36.0, $w - $textWidth - 36.0);
            $canvas->page_text($x, 810.0, $footer, $font, $size, $color);
        }

        $binary = $dompdf->output();
        $safeName = $this->sanitizeFilename($downloadBaseName);

        return new Response($binary, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$safeName.'.pdf"',
        ]);
    }

    public function getOptionalLogoDataUri(): ?string
    {
        foreach (['tabaani-logo.png', 'tabaani-logo.jpg', 'logo.png'] as $file) {
            $path = $this->projectDir.\DIRECTORY_SEPARATOR.'public'.\DIRECTORY_SEPARATOR.'branding'.\DIRECTORY_SEPARATOR.$file;
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }
            $bin = @file_get_contents($path);
            if (false === $bin || '' === $bin) {
                continue;
            }
            $mime = str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg';

            return 'data:'.$mime.';base64,'.base64_encode($bin);
        }

        return null;
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9._-]+/u', '-', $name) ?? 'export';
        $name = trim($name, '-');

        return '' !== $name ? $name : 'export';
    }
}
