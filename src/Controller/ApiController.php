<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Participant;
use App\Entity\Round;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use App\Repository\GameRepository;
use App\Repository\ParticipantRepository;
use App\Repository\TournamentMatchRepository;
use App\Repository\TournamentRepository;
use App\Service\SwissBracketService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ApiController extends AbstractController
{
    #[Route('/juegos', name: 'api_games_index', methods: ['GET'])]
    public function games(Request $request, GameRepository $gameRepository): JsonResponse
    {
        $payload = [];
        foreach ($gameRepository->findAll() as $game) {
            $payload[] = $this->serializeGame($game, $request);
        }

        return $this->apiResponse($payload);
    }

    #[Route('/torneos', name: 'api_tournaments_index', methods: ['GET'])]
    public function tournaments(Request $request, TournamentRepository $tournamentRepository, ParticipantRepository $participantRepository): JsonResponse
    {
        $tournaments = $tournamentRepository->findAllOrderedByCreatedAtDesc();
        $counts = $this->participantCounts($tournaments, $participantRepository);

        $payload = [];
        foreach ($tournaments as $tournament) {
            $payload[] = $this->serializeTournamentSummary($tournament, $request, $counts[$tournament->getId() ?? 0] ?? 0);
        }

        return $this->apiResponse($payload);
    }

    #[Route('/torneos/{id}', name: 'api_tournaments_show', methods: ['GET'])]
    public function tournament(int $id, Request $request, TournamentRepository $tournamentRepository, ParticipantRepository $participantRepository): JsonResponse
    {
        $tournament = $tournamentRepository->find($id);
        if (!$tournament instanceof Tournament) {
            return $this->notFoundResponse();
        }

        $participantCount = $participantRepository->countByTournament($tournament);

        $payload = $this->serializeTournamentSummary($tournament, $request, $participantCount);
        $payload['participants'] = [];
        foreach ($participantRepository->findByTournamentOrderedByRegisteredAtAsc($tournament) as $participant) {
            $payload['participants'][] = $this->serializeParticipant($participant);
        }
        $payload['champion'] = $tournament->getChampion() ? $this->serializeParticipant($tournament->getChampion()) : null;

        return $this->apiResponse($payload);
    }

    #[Route('/torneos/{id}/bracket', name: 'api_tournaments_bracket', methods: ['GET'])]
    public function tournamentBracket(
        int $id,
        TournamentRepository $tournamentRepository,
        TournamentMatchRepository $matchRepository,
        SwissBracketService $swissBracketService,
    ): JsonResponse {
        $tournament = $tournamentRepository->find($id);
        if (!$tournament instanceof Tournament) {
            return $this->notFoundResponse();
        }

        return match ($tournament->getFormat()) {
            'single_elim' => $this->apiResponse([
                'rounds' => $this->serializeSingleElimRounds($tournament, $matchRepository),
            ]),
            'swiss' => $this->apiResponse([
                'rounds' => $this->serializeSwissRounds($tournament, $matchRepository),
                'standings' => $this->serializeSwissStandings($swissBracketService->calculateStandings($tournament)),
            ]),
            default => $this->notFoundResponse(),
        };
    }

    private function serializeGame(?Game $game, Request $request): array
    {
        if (!$game instanceof Game) {
            return [
                'id' => null,
                'name' => null,
                'imagePath' => null,
            ];
        }

        return [
            'id' => $game->getId(),
            'name' => $game->getName(),
            'imagePath' => $this->absoluteUrl($request, $game->getImagePath()),
        ];
    }

    private function serializeTournamentSummary(Tournament $tournament, Request $request, int $participantsCount): array
    {
        return [
            'id' => $tournament->getId(),
            'name' => $tournament->getName(),
            'status' => $tournament->getStatus(),
            'format' => $tournament->getFormat(),
            'maxParticipants' => $tournament->getMaxParticipants(),
            'startAt' => $tournament->getStartAt()->format('Y-m-d\TH:i:s'),
            'registrationDeadlineAt' => $tournament->getRegistrationDeadlineAt()->format('Y-m-d\TH:i:s'),
            'participantsCount' => $participantsCount,
            'game' => $this->serializeGame($tournament->getGame(), $request),
            'organizer' => $this->serializeOrganizer($tournament),
        ];
    }

    private function serializeOrganizer(Tournament $tournament): array
    {
        $organizer = $tournament->getOrganizer();

        return [
            'id' => $organizer?->getId(),
            'username' => $organizer?->getUsername(),
        ];
    }

    private function serializeParticipant(Participant $participant): array
    {
        return [
            'id' => $participant->getId(),
            'username' => $participant->getUser()?->getUsername(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function participantCounts(array $tournaments, ParticipantRepository $participantRepository): array
    {
        $ids = [];
        foreach ($tournaments as $tournament) {
            if ($tournament instanceof Tournament && null !== $tournament->getId()) {
                $ids[] = $tournament->getId();
            }
        }

        return $participantRepository->countByTournamentIds($ids);
    }

    /**
     * @return array<int, array{number:int,name:string,matches:array<int, array<string, mixed>>}>
     */
    private function serializeSingleElimRounds(Tournament $tournament, TournamentMatchRepository $matchRepository): array
    {
        $matches = $matchRepository->findBy(['tournament' => $tournament], ['slot' => 'ASC']);

        $groups = [];
        foreach ($matches as $match) {
            $roundNumber = $match->getRound()?->getNumber() ?? 1;
            $groups[$roundNumber][] = $match;
        }

        ksort($groups);

        $payload = [];
        foreach ($groups as $roundNumber => $roundMatches) {
            $payload[] = [
                'number' => (int) $roundNumber,
                'name' => $this->singleElimRoundName($roundMatches, (int) $roundNumber),
                'matches' => $this->serializeMatches($roundMatches),
            ];
        }

        return $payload;
    }

    /**
     * @return array<int, array{number:int,matches:array<int, array<string, mixed>>}>
     */
    private function serializeSwissRounds(Tournament $tournament, TournamentMatchRepository $matchRepository): array
    {
        $matches = $matchRepository->findBy(['tournament' => $tournament], ['slot' => 'ASC']);

        $groups = [];
        foreach ($matches as $match) {
            $roundNumber = $match->getRound()?->getNumber() ?? 1;
            $groups[$roundNumber][] = $match;
        }

        ksort($groups);

        $payload = [];
        foreach ($groups as $roundNumber => $roundMatches) {
            $payload[] = [
                'number' => (int) $roundNumber,
                'matches' => $this->serializeMatches($roundMatches),
            ];
        }

        return $payload;
    }

    /**
     * @param TournamentMatch[] $matches
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeMatches(array $matches): array
    {
        $payload = [];
        foreach ($matches as $match) {
            $payload[] = [
                'id' => $match->getId(),
                'slot' => $match->getSlot(),
                'status' => $match->getStatus(),
                'participant1' => $match->getParticipant1() ? $this->serializeParticipant($match->getParticipant1()) : null,
                'participant2' => $match->getParticipant2() ? $this->serializeParticipant($match->getParticipant2()) : null,
                'winner' => $match->getWinner() ? $this->serializeParticipant($match->getWinner()) : null,
            ];
        }

        return $payload;
    }

    /**
     * @param array<int, array<string, mixed>> $standings
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeSwissStandings(array $standings): array
    {
        $payload = [];
        foreach (array_values($standings) as $index => $row) {
            $participant = $row['participant'] ?? null;
            if (!$participant instanceof Participant) {
                continue;
            }

            $payload[] = [
                'position' => $index + 1,
                'username' => $participant->getUser()?->getUsername(),
                'points' => (int) ($row['points'] ?? 0),
                'wins' => (int) ($row['wins'] ?? 0),
                'losses' => (int) ($row['losses'] ?? 0),
                'played' => (int) ($row['wins'] ?? 0) + (int) ($row['losses'] ?? 0),
            ];
        }

        return $payload;
    }

    private function singleElimRoundName(array $matches, int $roundNumber): string
    {
        $firstMatch = $matches[0] ?? null;
        $storedName = $firstMatch instanceof TournamentMatch ? $firstMatch->getRound()?->getName() : null;

        return $storedName ?: 'Ronda '.$roundNumber;
    }

    private function absoluteUrl(Request $request, ?string $path): ?string
    {
        if (null === $path || '' === trim($path)) {
            return null;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($path, '/');
    }

    private function apiResponse(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = $this->json($data, $status, [], [
            'json_encode_options' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ]);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');

        return $response;
    }

    private function notFoundResponse(): JsonResponse
    {
        return $this->apiResponse(['error' => 'No encontrado'], Response::HTTP_NOT_FOUND);
    }
}