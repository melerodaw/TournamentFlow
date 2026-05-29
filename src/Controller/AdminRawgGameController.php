<?php

namespace App\Controller;

use App\Service\RawgApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/juegos')]
#[IsGranted('ROLE_ADMIN')]
final class AdminRawgGameController extends AbstractController
{
    #[Route('/buscar-rawg', name: 'app_game_search_rawg', methods: ['GET'])]
    public function searchRawg(Request $request, RawgApiService $rawgApiService): JsonResponse
    {
        $query = (string) $request->query->get('q', '');

        try {
            return $this->json([
                'results' => $rawgApiService->searchGames($query),
            ]);
        } catch (\Throwable $exception) {
            return $this->json([
                'results' => [],
                'message' => 'No se pudo conectar con RAWG.',
            ]);
        }
    }
}