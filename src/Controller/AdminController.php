<?php

namespace App\Controller;

use App\Entity\Tournament;
use App\Repository\GameRepository;
use App\Repository\ParticipantRepository;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_index', methods: ['GET'])]
    public function index(GameRepository $gameRepository, TournamentRepository $tournamentRepository, ParticipantRepository $participantRepository): Response
    {
        $tournaments = $tournamentRepository->findAllOrderedByCreatedAtDesc();
        $ids = [];
        foreach ($tournaments as $tournament) {
            $id = $tournament->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        $counts = $participantRepository->countByTournamentIds($ids);
        $meta = [];
        foreach ($tournaments as $tournament) {
            $id = $tournament->getId();
            if (null === $id) {
                continue;
            }

            $participantCount = $counts[$id] ?? 0;
            $status = $tournament->getComputedStatus($participantCount);
            $meta[$id] = [
                'participant_count' => $participantCount,
                'status_label' => $tournament->getComputedStatusLabel($participantCount),
                'status_class' => $this->statusClass($status),
            ];
        }

        return $this->render('admin/index.html.twig', [
            'games' => $gameRepository->findBy([], ['name' => 'ASC']),
            'tournaments' => $tournaments,
            'tournament_meta' => $meta,
        ]);
    }

    #[Route('/tournament/{id}/delete', name: 'app_admin_tournament_delete', methods: ['POST'])]
    public function deleteTournament(Request $request, Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('admin_delete_tournament'.$tournament->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($tournament);
            $entityManager->flush();
            $this->addFlash('success', 'Torneo eliminado correctamente');
        }

        return $this->redirectToRoute('app_admin_index');
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            'abierto' => 'status-pill status-open',
            'lleno' => 'status-pill status-full',
            'finalizado' => 'status-pill status-finished',
            default => 'status-pill status-closed',
        };
    }
}