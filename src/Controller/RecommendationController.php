<?php

namespace App\Controller;

use App\Service\RecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RecommendationController extends AbstractController
{
    #[Route('/recommendations', name: 'app_recommendations')]
    public function index(Request $request, RecommendationService $recommendationService): Response
    {
        $ville = $request->query->get('ville');
        $budget = $request->query->get('budget');
        $type = $request->query->get('type');
        $categorie = $request->query->get('categorie');

        $data = $recommendationService->getRecommendations($ville, $budget, $type, $categorie);

        return $this->render('recommendation/index.html.twig', [
            'data' => $data,
            'filters' => [
                'ville' => $ville,
                'budget' => $budget,
                'type' => $type,
                'categorie' => $categorie,
            ]
        ]);
    }
}