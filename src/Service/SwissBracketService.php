<?php

namespace App\Service;

use App\Entity\Tournament;
use App\Entity\Round;
use App\Entity\TournamentMatch;
use App\Repository\ParticipantRepository;
use App\Repository\TournamentMatchRepository;
use Doctrine\ORM\EntityManagerInterface;

class SwissBracketService
{
    private EntityManagerInterface $em;
    private ParticipantRepository $participantRepository;
    private TournamentMatchRepository $matchRepository;

    public function __construct(EntityManagerInterface $em, ParticipantRepository $participantRepository, TournamentMatchRepository $matchRepository)
    {
        $this->em = $em;
        $this->participantRepository = $participantRepository;
        $this->matchRepository = $matchRepository;
    }

    public function generateFirstRound(Tournament $tournament): void
    {
        $participants = $this->participantRepository->findByTournamentOrderedByRegisteredAtAsc($tournament);
        $count = count($participants);
        if ($count < 4) {
            throw new \RuntimeException('Se necesitan al menos 4 participantes para generar Swiss.');
        }

        // Fisher-Yates shuffle
        $list = $participants;
        for ($i = count($list) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $list[$i];
            $list[$i] = $list[$j];
            $list[$j] = $tmp;
        }

        $round = new Round();
        $round->setTournament($tournament);
        $round->setNumber(1);
        $round->setName('Ronda 1');
        $this->em->persist($round);

        $matchCount = intdiv($count, 2);
        for ($i = 0; $i < $matchCount; $i++) {
            $m = new TournamentMatch();
            $m->setTournament($tournament);
            $m->setRound($round);
            $m->setSlot($i);
            $m->setStatus('pending');
            $m->setParticipant1($list[$i * 2]);
            $m->setParticipant2($list[$i * 2 + 1]);
            $this->em->persist($m);
        }

        if ($count % 2 === 1) {
            // bye for last participant
            $byeParticipant = $list[$count - 1];
            $m = new TournamentMatch();
            $m->setTournament($tournament);
            $m->setRound($round);
            $m->setSlot($matchCount);
            $m->setStatus('completed');
            $m->setParticipant1($byeParticipant);
            $m->setParticipant2(null);
            $m->setWinner($byeParticipant);
            $m->setPlayedAt(new \DateTimeImmutable());
            $this->em->persist($m);
        }

        $this->em->flush();
    }

    public function generateNextRound(Tournament $tournament): void
    {
        // collect participants
        $participants = $this->participantRepository->findByTournamentOrderedByRegisteredAtAsc($tournament);
        $participantIds = array_map(fn($p) => $p->getId(), $participants);

        // compute current wins
        $wins = [];
        foreach ($participants as $p) {
            $wins[$p->getId()] = 0;
        }

        $matches = $this->matchRepository->findBy(['tournament' => $tournament]);
        foreach ($matches as $m) {
            $w = $m->getWinner();
            if ($w) {
                $id = $w->getId();
                $wins[$id] = ($wins[$id] ?? 0) + 1;
            }
        }

        // group by wins
        $groups = [];
        foreach ($participants as $p) {
            $key = $wins[$p->getId()] ?? 0;
            $groups[$key][] = $p;
        }

        krsort($groups); // higher wins first

        // build previous opponents map
        $opponents = [];
        foreach ($participants as $p) {
            $opponents[$p->getId()] = [];
        }
        foreach ($matches as $m) {
            $p1 = $m->getParticipant1();
            $p2 = $m->getParticipant2();
            if ($p1 && $p2) {
                $opponents[$p1->getId()][$p2->getId()] = true;
                $opponents[$p2->getId()][$p1->getId()] = true;
            }
        }

        $roundNumber = count($tournament->getRounds()) + 1;
        $round = new Round();
        $round->setTournament($tournament);
        $round->setNumber($roundNumber);
        $round->setName('Ronda ' . $roundNumber);
        $this->em->persist($round);

        $createdMatches = [];

        // simple greedy pairing within groups
        foreach ($groups as $group) {
            $list = $group;
            // shuffle
            for ($i = count($list) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                $tmp = $list[$i];
                $list[$i] = $list[$j];
                $list[$j] = $tmp;
            }

            while (count($list) >= 2) {
                $a = array_shift($list);
                // find opponent not played before
                $foundIndex = null;
                foreach ($list as $idx => $cand) {
                    if (!isset($opponents[$a->getId()][$cand->getId()])) {
                        $foundIndex = $idx;
                        break;
                    }
                }
                if ($foundIndex === null) {
                    // no suitable, pair with first
                    $b = array_shift($list);
                } else {
                    $b = $list[$foundIndex];
                    array_splice($list, $foundIndex, 1);
                }

                $m = new TournamentMatch();
                $m->setTournament($tournament);
                $m->setRound($round);
                $m->setSlot(count($createdMatches));
                $m->setStatus('pending');
                $m->setParticipant1($a);
                $m->setParticipant2($b);
                $this->em->persist($m);
                $createdMatches[] = $m;
            }

            if (count($list) === 1) {
                // unmatched, will be bye: create match with participant2 null and winner assigned
                $solo = array_shift($list);
                $m = new TournamentMatch();
                $m->setTournament($tournament);
                $m->setRound($round);
                $m->setSlot(count($createdMatches));
                $m->setStatus('completed');
                $m->setParticipant1($solo);
                $m->setParticipant2(null);
                $m->setWinner($solo);
                $m->setPlayedAt(new \DateTimeImmutable());
                $this->em->persist($m);
                $createdMatches[] = $m;
            }
        }

        $this->em->flush();
    }

    public function calculateStandings(Tournament $tournament): array
    {
        $participants = $this->participantRepository->findByTournamentOrderedByRegisteredAtAsc($tournament);
        $matches = $this->matchRepository->findBy(['tournament' => $tournament]);

        $stats = [];
        foreach ($participants as $p) {
            $stats[$p->getId()] = ['participant' => $p, 'points' => 0, 'wins' => 0, 'losses' => 0, 'opponents' => []];
        }

        foreach ($matches as $m) {
            $p1 = $m->getParticipant1();
            $p2 = $m->getParticipant2();
            $w = $m->getWinner();
            if ($p1 && $p2) {
                $stats[$p1->getId()]['opponents'][] = $p2->getId();
                $stats[$p2->getId()]['opponents'][] = $p1->getId();
            }
            if ($w) {
                $stats[$w->getId()]['points'] += 1;
                $stats[$w->getId()]['wins'] += 1;
                // mark loss for loser
                if ($p1 && $p2) {
                    $loser = $p1->getId() === $w->getId() ? $p2 : $p1;
                    $stats[$loser->getId()]['losses'] += 1;
                }
            }
        }

        // buchholz: sum of opponents points
        foreach ($stats as $id => &$row) {
            $buch = 0;
            foreach ($row['opponents'] as $oppId) {
                $buch += $stats[$oppId]['points'] ?? 0;
            }
            $row['buchholz'] = $buch;
        }
        unset($row);

        // sort by points desc, buchholz desc
        usort($stats, function ($a, $b) {
            if ($a['points'] === $b['points']) {
                return $b['buchholz'] <=> $a['buchholz'];
            }
            return $b['points'] <=> $a['points'];
        });

        return $stats;
    }

    public function isSwissComplete(Tournament $tournament): bool
    {
        $roundCount = count($tournament->getRounds());
        $planned = $tournament->getSwissRounds() ?? 0;
        return $planned > 0 && $roundCount >= $planned;
    }
}
