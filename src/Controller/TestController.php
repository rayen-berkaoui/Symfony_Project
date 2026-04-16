<?php

namespace App\Controller;

use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test-traduction', name: 'test_traduction')]
    public function test(TranslationService $translator): Response
    {
        $result = $translator->translate('Bonjour je suis étudiant', 'fr', 'en');

        return new Response($result ?? 'Aucune traduction');
    }
}