<?php

namespace App\Controller;

use App\Entity\Tournament;
use App\Repository\TournamentMatchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/torneo')]
final class AdminTournamentResultsController extends AbstractController
{
    #[Route('/{id}/resultados', name: 'app_admin_tournament_results', methods: ['GET'])]
    public function index(Tournament $tournament, TournamentMatchRepository $matchRepository): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException();
        }

        $matches = $matchRepository->findBy(['tournament' => $tournament], ['slot' => 'ASC']);
        $groupedRounds = [];
        foreach ($matches as $match) {
            $roundNumber = $match->getRound() ? $match->getRound()->getNumber() : 1;
            $groupedRounds[$roundNumber][] = $match;
        }
        ksort($groupedRounds);

        return $this->render('admin/tournament/results.html.twig', [
            'tournament' => $tournament,
            'rounds' => $groupedRounds,
        ]);
    }
}
