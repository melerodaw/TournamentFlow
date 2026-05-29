<?php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Repository\TournamentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_landing')]
    public function landing(GameRepository $gameRepository, TournamentRepository $tournamentRepository): Response
    {
        return $this->renderLanding($gameRepository, $tournamentRepository);
    }

    #[Route('/home', name: 'app_home')]
    public function index(GameRepository $gameRepository, TournamentRepository $tournamentRepository): Response
    {
        if (!$this->getUser()) {
            return $this->renderLanding($gameRepository, $tournamentRepository);
        }

        return $this->render('home/index.html.twig');
    }

    private function renderLanding(GameRepository $gameRepository, TournamentRepository $tournamentRepository): Response
    {
        $games = $gameRepository->findBy([], ['name' => 'ASC']);
        $recentTournaments = array_slice($tournamentRepository->findAllOrderedByCreatedAtDesc(), 0, 4);
        $featuredGames = array_slice($games, 0, 6);
        $featuredGameStats = [];

        foreach ($featuredGames as $game) {
            $activeCount = 0;

            foreach ($game->getTournaments() as $tournament) {
                if (in_array($tournament->getStatus(), ['open', 'running'], true)) {
                    ++$activeCount;
                }
            }

            $featuredGameStats[$game->getId()] = $activeCount;
        }

        return $this->render('home/landing.html.twig', [
            'featured_games' => $featuredGames,
            'featured_game_stats' => $featuredGameStats,
            'recent_tournaments' => $recentTournaments,
        ]);
    }
}
