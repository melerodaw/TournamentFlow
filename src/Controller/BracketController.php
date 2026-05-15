<?php

namespace App\Controller;

use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Repository\ParticipantRepository;
use App\Repository\TournamentMatchRepository;
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

        return $this->render('tournament/bracket.html.twig', [
            'tournament' => $tournament,
            'rounds' => $groups,
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

    private function roundName(int $participantCount, int $roundNumber): string
    {
        $roundsRemaining = (int) log($participantCount, 2) - $roundNumber + 1;

        return match ($roundsRemaining) {
            1 => 'Final',
            2 => 'Semifinales',
            3 => 'Cuartos',
            4 => 'Octavos',
            default => 'Ronda ' . $roundNumber,
        };
    }
}
