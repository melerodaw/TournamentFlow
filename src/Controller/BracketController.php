<?php

namespace App\Controller;

use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Repository\ParticipantRepository;
use App\Repository\TournamentMatchRepository;
use App\Service\SwissBracketService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/torneo')]
final class BracketController extends AbstractController
{
    #[Route('/{id}/bracket', name: 'app_tournament_bracket', methods: ['GET'])]
    public function bracket(Tournament $tournament, TournamentMatchRepository $matchRepository): Response
    {
        $matches = $matchRepository->findBy(['tournament' => $tournament], ['slot' => 'ASC']);

        // group by round number (round may be null for legacy matches)
        $groups = [];
        foreach ($matches as $m) {
            $r = $m->getRound() ? $m->getRound()->getNumber() : 1;
            $groups[$r][] = $m;
        }

        ksort($groups);

        // build human friendly round labels (use stored round name when available)
        $roundLabels = [];
        $totalRounds = count($groups);
        foreach ($groups as $roundNumber => $matchesInRound) {
            $firstMatch = $matchesInRound[0] ?? null;
            $storedName = $firstMatch && $firstMatch->getRound() ? $firstMatch->getRound()->getName() : null;
            $roundLabels[$roundNumber] = $storedName ?: $this->displayRoundLabel($totalRounds, $roundNumber);
        }

        return $this->render('tournament/bracket.html.twig', [
            'tournament' => $tournament,
            'rounds' => $groups,
            'round_labels' => $roundLabels,
        ]);
    }

    #[Route('/{id}/generar-bracket', name: 'app_tournament_generate_bracket', methods: ['POST'])]
    public function generate(Request $request, Tournament $tournament, ParticipantRepository $participantRepository, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || ($tournament->getOrganizer()?->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles(), true))) {
            throw $this->createAccessDeniedException();
        }

        if ($tournament->getStatus() !== 'open') {
            $this->addFlash('warning', 'El bracket solo se puede generar si el torneo está en estado "open".');

            return $this->redirectToRoute('app_tournament_show', ['id' => $tournament->getId()]);
        }

        $participants = $participantRepository->findByTournamentOrderedByRegisteredAtAsc($tournament);
        $count = count($participants);
        if ($count < 4) {
            $this->addFlash('warning', 'Se necesitan al menos 4 participantes para generar el bracket.');

            return $this->redirectToRoute('app_tournament_show', ['id' => $tournament->getId()]);
        }

        // nearest power of two
        $pow = 1;
        while ($pow < $count) {
            $pow <<= 1;
        }
        if ($pow !== $count) {
            $this->addFlash('warning', sprintf('El número de participantes (%d) no es potencia de 2. Se requieren %d (potencia de 2 más cercana).', $count, $pow));

            return $this->redirectToRoute('app_tournament_show', ['id' => $tournament->getId()]);
        }

        // Fisher-Yates shuffle
        $list = $participants;
        for ($i = count($list) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $list[$i];
            $list[$i] = $list[$j];
            $list[$j] = $tmp;
        }

        $totalRounds = (int) log($count, 2);
        $participantsPerRound = $count;

        for ($roundNumber = 1; $roundNumber <= $totalRounds; $roundNumber++) {
            $round = new Round();
            $round->setTournament($tournament);
            $round->setNumber($roundNumber);
            $round->setName($this->roundName($count, $roundNumber));
            $em->persist($round);

            $matchCount = (int) ($participantsPerRound / 2);
            for ($slot = 0; $slot < $matchCount; $slot++) {
                $match = new TournamentMatch();
                $match->setTournament($tournament);
                $match->setRound($round);
                $match->setSlot($slot);
                $match->setStatus('pending');

                if (1 === $roundNumber) {
                    $participantIndex = $slot * 2;
                    $match->setParticipant1($list[$participantIndex]);
                    $match->setParticipant2($list[$participantIndex + 1]);
                }

                $em->persist($match);
            }

            $participantsPerRound = (int) ($participantsPerRound / 2);
        }

        $tournament->setStatus('in_progress');
        $em->flush();

        $this->addFlash('success', 'Bracket generado correctamente.');

        return $this->redirectToRoute('app_tournament_bracket', ['id' => $tournament->getId()]);
    }

    #[Route('/{id}/match/{matchId}/result', name: 'app_tournament_match_result', methods: ['POST'])]
    public function recordResult(Request $request, Tournament $tournament, int $matchId, TournamentMatchRepository $matchRepository, ParticipantRepository $participantRepository, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || ($tournament->getOrganizer()?->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles(), true))) {
            throw $this->createAccessDeniedException();
        }

        $match = $matchRepository->find($matchId);
        if (!$match || $match->getTournament()->getId() !== $tournament->getId()) {
            $this->addFlash('error', 'Partido no encontrado.');

            return $this->redirectToRoute('app_tournament_bracket', ['id' => $tournament->getId()]);
        }

        if ($match->getWinner() !== null) {
            $this->addFlash('warning', 'Este partido ya tiene un ganador.');

            return $this->redirectToRoute('app_tournament_bracket', ['id' => $tournament->getId()]);
        }

        $winnerId = (int) $request->request->get('winner_id');
        $winner = $participantRepository->find($winnerId);
        if (!$winner) {
            $this->addFlash('error', 'Participante inválido.');

            return $this->redirectToRoute('app_tournament_bracket', ['id' => $tournament->getId()]);
        }

        // ensure participant belongs to tournament
        if ($winner->getTournament()->getId() !== $tournament->getId()) {
            $this->addFlash('error', 'El participante no está inscrito en este torneo.');

            return $this->redirectToRoute('app_tournament_bracket', ['id' => $tournament->getId()]);
        }

        $match->setWinner($winner);
        $match->setStatus('completed');
        $match->setPlayedAt(new \DateTimeImmutable());

        // mark matchParticipants winner flags if present
        foreach ($match->getMatchParticipants() as $mp) {
            $mp->setIsWinner($mp->getParticipant() && $mp->getParticipant()->getId() === $winner->getId());
        }

        $round = $match->getRound();
        $currentRoundNumber = $round ? $round->getNumber() : 1;
        $lastRoundNumber = 1;
        foreach ($tournament->getRounds() as $roundItem) {
            $lastRoundNumber = max($lastRoundNumber, $roundItem->getNumber());
        }

        if ($currentRoundNumber >= $lastRoundNumber) {
            $tournament->setChampion($winner);
            $tournament->setStatus('finished');
            $em->flush();
            $this->addFlash('success', 'Se ha finalizado el torneo. Campeón: ' . $winner->getUser()->getUsername());

            return $this->redirectToRoute('app_tournament_bracket', ['id' => $tournament->getId()]);
        }

        $nextRoundNumber = $currentRoundNumber + 1;

        $nextRound = null;
        foreach ($tournament->getRounds() as $candidateRound) {
            if ($candidateRound->getNumber() === $nextRoundNumber) {
                $nextRound = $candidateRound;
                break;
            }
        }

        if (!$nextRound) {
            $this->addFlash('error', 'No se pudo localizar la siguiente ronda del bracket.');

            return $this->redirectToRoute('app_tournament_bracket', ['id' => $tournament->getId()]);
        }

        $nextSlot = intdiv($match->getSlot(), 2);
        $nextMatch = null;
        foreach ($nextRound->getMatches() as $nm) {
            if ($nm->getSlot() === $nextSlot) {
                $nextMatch = $nm;
                break;
            }
        }

        if (!$nextMatch) {
            $this->addFlash('error', 'No se pudo localizar el siguiente partido del bracket.');

            return $this->redirectToRoute('app_tournament_bracket', ['id' => $tournament->getId()]);
        }

        // place winner into correct slot
        if ($match->getSlot() % 2 === 0) {
            $nextMatch->setParticipant1($winner);
        } else {
            $nextMatch->setParticipant2($winner);
        }

        $em->flush();

        $this->addFlash('success', 'Resultado guardado y ganador avanzado.');

        return $this->redirectToRoute('app_tournament_bracket', ['id' => $tournament->getId()]);
    }



    #[Route('/{id}/swiss/generar-ronda', name: 'app_tournament_swiss_generate_round', methods: ['POST'])]
    public function swissGenerate(Request $request, Tournament $tournament, SwissBracketService $swissService, ParticipantRepository $participantRepository, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || ($tournament->getOrganizer()?->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles(), true))) {
            throw $this->createAccessDeniedException();
        }

        if ($tournament->getFormat() !== 'swiss') {
            $this->addFlash('warning', 'Este endpoint es solo para torneos en formato Swiss.');

            return $this->redirectToRoute('app_tournament_show', ['id' => $tournament->getId()]);
        }

        $participantCount = $participantRepository->countByTournament($tournament);
        if ($participantCount < 4) {
            $this->addFlash('warning', 'Se necesitan al menos 4 participantes para generar rondas Swiss.');

            return $this->redirectToRoute('app_tournament_swiss_bracket', ['id' => $tournament->getId()]);
        }

        $roundCount = count($tournament->getRounds());
        $planned = $tournament->getSwissRounds() ?? (int) ceil(log($participantCount, 2));

        if ($roundCount >= $planned) {
            $this->addFlash('warning', 'Ya se han generado todas las rondas previstas.');

            return $this->redirectToRoute('app_tournament_swiss_bracket', ['id' => $tournament->getId()]);
        }

        if ($roundCount === 0) {
            $swissService->generateFirstRound($tournament);
        } else {
            // ensure last round completed
            $lastRoundNumber = 0;
            foreach ($tournament->getRounds() as $r) {
                $lastRoundNumber = max($lastRoundNumber, $r->getNumber());
            }
            foreach ($tournament->getRounds() as $r) {
                if ($r->getNumber() === $lastRoundNumber) {
                    foreach ($r->getMatches() as $m) {
                        if ($m->getStatus() !== 'completed') {
                            $this->addFlash('warning', 'No se pueden generar nuevas rondas hasta que todos los partidos de la ronda actual estén completados.');

                            return $this->redirectToRoute('app_tournament_swiss_bracket', ['id' => $tournament->getId()]);
                        }
                    }
                }
            }

            $swissService->generateNextRound($tournament);
        }

        $tournament->setStatus('in_progress');
        $em->flush();

        $this->addFlash('success', 'Ronda generada correctamente.');

        return $this->redirectToRoute('app_tournament_swiss_bracket', ['id' => $tournament->getId()]);
    }

    #[Route('/{id}/swiss/bracket', name: 'app_tournament_swiss_bracket', methods: ['GET'])]
    public function swissBracket(Tournament $tournament, TournamentMatchRepository $matchRepository, SwissBracketService $swissService): Response
    {
        if ($tournament->getFormat() !== 'swiss') {
            return $this->redirectToRoute('app_tournament_bracket', ['id' => $tournament->getId()]);
        }

        $matches = $matchRepository->findBy(['tournament' => $tournament], ['round' => 'ASC', 'slot' => 'ASC']);
        $groups = [];
        foreach ($matches as $m) {
            $r = $m->getRound() ? $m->getRound()->getNumber() : 1;
            $groups[$r][] = $m;
        }
        ksort($groups);

        $standings = $swissService->calculateStandings($tournament);

        return $this->render('tournament/swiss_bracket.html.twig', [
            'tournament' => $tournament,
            'rounds' => $groups,
            'standings' => $standings,
        ]);
    }

    #[Route('/{id}/swiss/resultado/{matchId}', name: 'app_tournament_swiss_result', methods: ['POST'])]
    public function swissResult(Request $request, Tournament $tournament, int $matchId, TournamentMatchRepository $matchRepository, ParticipantRepository $participantRepository, EntityManagerInterface $em, SwissBracketService $swissService): Response
    {
        $user = $this->getUser();
        if (!$user || ($tournament->getOrganizer()?->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles(), true))) {
            throw $this->createAccessDeniedException();
        }

        $match = $matchRepository->find($matchId);
        if (!$match || $match->getTournament()->getId() !== $tournament->getId()) {
            $this->addFlash('error', 'Partido no encontrado.');

            return $this->redirectToRoute('app_tournament_swiss_bracket', ['id' => $tournament->getId()]);
        }

        if ($match->getWinner() !== null) {
            $this->addFlash('warning', 'Este partido ya tiene un ganador.');

            return $this->redirectToRoute('app_tournament_swiss_bracket', ['id' => $tournament->getId()]);
        }

        $winnerId = (int) $request->request->get('winner_id');
        $winner = $participantRepository->find($winnerId);
        if (!$winner) {
            $this->addFlash('error', 'Participante inválido.');

            return $this->redirectToRoute('app_tournament_swiss_bracket', ['id' => $tournament->getId()]);
        }

        if ($winner->getTournament()->getId() !== $tournament->getId()) {
            $this->addFlash('error', 'El participante no está inscrito en este torneo.');

            return $this->redirectToRoute('app_tournament_swiss_bracket', ['id' => $tournament->getId()]);
        }

        $match->setWinner($winner);
        $match->setStatus('completed');
        $match->setPlayedAt(new \DateTimeImmutable());

        $em->flush();

        // if swiss complete, finalize
        if ($swissService->isSwissComplete($tournament)) {
            $standings = $swissService->calculateStandings($tournament);
            if (!empty($standings)) {
                $champ = $standings[0]['participant'] ?? null;
                if ($champ) {
                    $tournament->setChampion($champ);
                    $tournament->setStatus('finished');
                    $em->flush();
                    $this->addFlash('success', 'Torneo finalizado. Campeón: ' . $champ->getUser()->getUsername());
                }
            }
        }

        $this->addFlash('success', 'Resultado registrado.');

        return $this->redirectToRoute('app_tournament_swiss_bracket', ['id' => $tournament->getId()]);
    }

    private function roundName(int $participantCount, int $roundNumber): string
    {
        $roundsRemaining = (int) log($participantCount, 2) - $roundNumber + 1;

        return match ($roundsRemaining) {
            1 => 'Final',
            2 => 'Semifinales',
            3 => 'Cuartos de final',
            4 => 'Octavos de final',
            5 => 'Dieciseisavos de final',
            6 => 'Treintaidosavos de final',
            default => 'Ronda ' . $roundNumber,
        };
    }

    private function displayRoundLabel(int $totalRounds, int $roundNumber): string
    {
        $roundsRemaining = $totalRounds - $roundNumber + 1;

        return match ($roundsRemaining) {
            1 => 'Final',
            2 => 'Semifinales',
            3 => 'Cuartos de final',
            4 => 'Octavos de final',
            5 => 'Dieciseisavos de final',
            6 => 'Treintaidosavos de final',
            default => 'Ronda ' . $roundNumber,
        };
    }
}
