<?php

namespace App\Controller;

use App\Entity\Participant;
use App\Entity\Tournament;
use App\Entity\User;
use App\Form\TournamentType;
use App\Repository\GameRepository;
use App\Repository\ParticipantRepository;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tournament')]
final class TournamentController extends AbstractController
{
    #[Route(name: 'app_tournament_index', methods: ['GET'])]
    public function index(Request $request, TournamentRepository $tournamentRepository, ParticipantRepository $participantRepository, GameRepository $gameRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $gameId = (int) $request->query->get('game_id', 0);
        $state = trim((string) $request->query->get('state', ''));
        $slots = trim((string) $request->query->get('slots', ''));

        $tournaments = $tournamentRepository->findForListing('' === $search ? null : $search, $gameId > 0 ? $gameId : null);
        $tournamentMeta = $this->buildTournamentMeta($tournaments, $participantRepository);
        $tournaments = $this->applyComputedFilters($tournaments, $tournamentMeta, $state, $slots);

        return $this->render('tournament/index.html.twig', [
            'tournaments' => $tournaments,
            'tournament_meta' => $tournamentMeta,
            'games' => $gameRepository->findBy([], ['name' => 'ASC']),
            'filters' => [
                'q' => $search,
                'game_id' => $gameId,
                'state' => $state,
                'slots' => $slots,
            ],
            'section_title' => 'Torneos',
            'section_subtitle' => 'Explora todos los torneos creados por la comunidad.',
            'active_tab' => 'all',
        ]);
    }

    #[Route('/mis-torneos', name: 'app_tournament_mine', methods: ['GET'])]
    public function mine(TournamentRepository $tournamentRepository, ParticipantRepository $participantRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $createdTournaments = $tournamentRepository->findByOrganizerOrderedByCreatedAtDesc($user);
        $joinedParticipations = $participantRepository->findByUserOrderedByRegisteredAtDesc($user);
        $joinedTournaments = array_map(static fn (Participant $participant): Tournament => $participant->getTournament(), $joinedParticipations);
        $tournamentMeta = $this->buildTournamentMeta(array_merge($createdTournaments, $joinedTournaments), $participantRepository);

        return $this->render('tournament/index.html.twig', [
            'created_tournaments' => $createdTournaments,
            'joined_participations' => $joinedParticipations,
            'tournament_meta' => $tournamentMeta,
            'section_title' => 'Mis torneos',
            'section_subtitle' => 'Gestiona los torneos que has creado y aquellos en los que participas.',
            'active_tab' => 'mine',
        ]);
    }

    #[Route('/new', name: 'app_tournament_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $tournament = new Tournament();
        $tournament->setOrganizer($user);
        $form = $this->createForm(TournamentType::class, $tournament);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tournament);
            $entityManager->flush();
            $this->addFlash('success', 'Torneo creado correctamente');

            return $this->redirectToRoute('app_tournament_mine', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tournament/new.html.twig', [
            'tournament' => $tournament,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tournament_show', methods: ['GET'])]
    public function show(Tournament $tournament, ParticipantRepository $participantRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $participantCount = $participantRepository->countByTournament($tournament);
        $isCreator = $tournament->getOrganizer()?->getId() === $user->getId();
        $participant = $participantRepository->findOneByTournamentAndUser($tournament, $user);
        $isRegistered = null !== $participant;
        $computedStatus = $tournament->getComputedStatus($participantCount);

        return $this->render('tournament/show.html.twig', [
            'tournament' => $tournament,
            'participants' => $isRegistered ? $participantRepository->findByTournamentOrderedByRegisteredAtAsc($tournament) : [],
            'participant_count' => $participantCount,
            'is_creator' => $isCreator,
            'is_registered' => $isRegistered,
            'can_join' => !$isRegistered && $tournament->isOpenForRegistration($participantCount),
            'can_manage' => $isCreator || in_array('ROLE_ADMIN', $user->getRoles(), true),
            'computed_status' => $computedStatus,
            'computed_status_label' => $tournament->getComputedStatusLabel($participantCount),
            'computed_status_class' => $this->statusClass($computedStatus),
        ]);
    }

    #[Route('/{id}/join', name: 'app_tournament_join', methods: ['POST'])]
    public function join(Request $request, Tournament $tournament, ParticipantRepository $participantRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('join'.$tournament->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_tournament_show', ['id' => $tournament->getId()], Response::HTTP_SEE_OTHER);
        }

        $participantCount = $participantRepository->countByTournament($tournament);
        $isRegistered = null !== $participantRepository->findOneByTournamentAndUser($tournament, $user);

        if ($isRegistered || !$tournament->isOpenForRegistration($participantCount)) {
            return $this->redirectToRoute('app_tournament_show', ['id' => $tournament->getId()], Response::HTTP_SEE_OTHER);
        }

        $participant = new Participant();
        $participant->setUser($user);
        $participant->setTournament($tournament);

        $entityManager->persist($participant);
        $entityManager->flush();
        $this->addFlash('success', 'Te has unido al torneo correctamente');

        return $this->redirectToRoute('app_tournament_show', ['id' => $tournament->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/leave', name: 'app_tournament_leave', methods: ['POST'])]
    public function leave(Request $request, Tournament $tournament, ParticipantRepository $participantRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('leave'.$tournament->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_tournament_mine', [], Response::HTTP_SEE_OTHER);
        }

        $participant = $participantRepository->findOneByTournamentAndUser($tournament, $user);
        if (null !== $participant && $participant->getMatchParticipants()->isEmpty()) {
            $entityManager->remove($participant);
            $entityManager->flush();
            $this->addFlash('success', 'Has abandonado el torneo');
        }

        return $this->redirectToRoute('app_tournament_mine', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_tournament_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canManageTournament($tournament)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(TournamentType::class, $tournament);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Cambios guardados');

            return $this->redirectToRoute('app_tournament_mine', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tournament/edit.html.twig', [
            'tournament' => $tournament,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tournament_delete', methods: ['POST'])]
    public function delete(Request $request, Tournament $tournament, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canManageTournament($tournament)) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$tournament->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($tournament);
            $entityManager->flush();
            $this->addFlash('success', 'Torneo eliminado correctamente');
        }

        return $this->redirectToRoute('app_tournament_mine', [], Response::HTTP_SEE_OTHER);
    }

    private function canManageTournament(Tournament $tournament): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $isCreator = $tournament->getOrganizer()?->getId() === $user->getId();

        return $isCreator || in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    /**
     * @param Tournament[] $tournaments
     *
     * @return array<int, array{participant_count:int,status:string,status_label:string,status_class:string,has_slots:bool}>
     */
    private function buildTournamentMeta(array $tournaments, ParticipantRepository $participantRepository): array
    {
        $tournamentIds = [];
        foreach ($tournaments as $tournament) {
            $id = $tournament->getId();
            if (null !== $id) {
                $tournamentIds[] = $id;
            }
        }

        $tournamentIds = array_values(array_unique($tournamentIds));
        $counts = $participantRepository->countByTournamentIds($tournamentIds);
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
                'status' => $status,
                'status_label' => $tournament->getComputedStatusLabel($participantCount),
                'status_class' => $this->statusClass($status),
                'has_slots' => $participantCount < $tournament->getMaxParticipants(),
            ];
        }

        return $meta;
    }

    /**
     * @param Tournament[] $tournaments
     * @param array<int, array{participant_count:int,status:string,status_label:string,status_class:string,has_slots:bool}> $meta
     *
     * @return Tournament[]
     */
    private function applyComputedFilters(array $tournaments, array $meta, string $state, string $slots): array
    {
        return array_values(array_filter($tournaments, static function (Tournament $tournament) use ($meta, $state, $slots): bool {
            $id = $tournament->getId();
            if (null === $id || !isset($meta[$id])) {
                return true;
            }

            $itemMeta = $meta[$id];

            if ('' !== $state && $itemMeta['status'] !== $state) {
                return false;
            }

            if ('with_slots' === $slots && !$itemMeta['has_slots']) {
                return false;
            }

            if ('without_slots' === $slots && $itemMeta['has_slots']) {
                return false;
            }

            return true;
        }));
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
