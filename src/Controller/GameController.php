<?php

namespace App\Controller;

use App\Entity\Game;
use App\Form\GameType;
use App\Repository\GameRepository;
use App\Service\RawgApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/game')]
final class GameController extends AbstractController
{
    #[Route(name: 'app_game_index', methods: ['GET'])]
    public function index(GameRepository $gameRepository): Response
    {
        return $this->render('game/index.html.twig', [
            'games' => $gameRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_game_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager, RawgApiService $rawgApiService, GameRepository $gameRepository): Response
    {
        $game = new Game();
        $form = $this->createForm(GameType::class, $game);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rawgId = (int) $request->request->get('rawg_id', 0);
            /** @var UploadedFile|null $uploaded */
            $uploaded = $form->get('imageFile')->getData();

            if ($rawgId > 0) {
                $existingRawg = $gameRepository->findOneBy(['rawgId' => $rawgId]);
                if ($existingRawg instanceof Game) {
                    $this->addFlash('error', 'Ese juego de RAWG ya existe en el catálogo.');

                    return $this->render('game/new.html.twig', [
                        'game' => $game,
                        'form' => $form,
                    ]);
                }

                try {
                    $rawgGame = $rawgApiService->getGame($rawgId);
                } catch (\Throwable $exception) {
                    $this->addFlash('error', 'No se pudo importar el juego desde RAWG.');

                    return $this->render('game/new.html.twig', [
                        'game' => $game,
                        'form' => $form,
                    ]);
                }

                $game->setRawgId($rawgGame['id']);

                if (trim($game->getName()) === '') {
                    $game->setName($rawgGame['name']);
                }

                if (trim((string) $game->getDescription()) === '') {
                    $game->setDescription(mb_substr((string) ($rawgGame['description_raw'] ?? ''), 0, 300));
                }

                $rawgImage = $rawgGame['background_image'] ?? null;
                if (is_string($rawgImage) && $rawgImage !== '') {
                    try {
                        $game->setImagePath($rawgApiService->downloadImage($rawgImage, $game->getName()));
                    } catch (\Throwable $exception) {
                        if ($uploaded instanceof UploadedFile) {
                            $imagesDir = $this->getParameter('kernel.project_dir').'/public/images/games';
                            if (!is_dir($imagesDir)) {
                                @mkdir($imagesDir, 0755, true);
                            }
                            $filename = uniqid('game_', true).'.'.($uploaded->guessExtension() ?: $uploaded->getClientOriginalExtension());
                            $uploaded->move($imagesDir, $filename);
                            $game->setImagePath('images/games/'.$filename);
                        }
                    }
                }
            } elseif ($uploaded instanceof UploadedFile) {
                $imagesDir = $this->getParameter('kernel.project_dir').'/public/images/games';
                if (!is_dir($imagesDir)) {
                    @mkdir($imagesDir, 0755, true);
                }
                $filename = uniqid('game_', true).'.'.($uploaded->guessExtension() ?: $uploaded->getClientOriginalExtension());
                $uploaded->move($imagesDir, $filename);
                $game->setImagePath('images/games/'.$filename);
            }

            if ($gameRepository->findOneBy(['name' => $game->getName()]) instanceof Game) {
                $this->addFlash('error', 'Ya existe un juego con ese nombre.');

                return $this->render('game/new.html.twig', [
                    'game' => $game,
                    'form' => $form,
                ]);
            }

            $entityManager->persist($game);
            $entityManager->flush();

            return $this->redirectToRoute('app_game_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('game/new.html.twig', [
            'game' => $game,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_game_show', methods: ['GET'])]
    public function show(Game $game): Response
    {
        return $this->render('game/show.html.twig', [
            'game' => $game,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_game_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Game $game, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(GameType::class, $game);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $uploaded */
            $uploaded = $form->get('imageFile')->getData();
            if ($uploaded instanceof UploadedFile) {
                $imagesDir = $this->getParameter('kernel.project_dir').'/public/images/games';
                if (!is_dir($imagesDir)) {
                    @mkdir($imagesDir, 0755, true);
                }
                $filename = uniqid('game_', true).'.'.($uploaded->guessExtension() ?: $uploaded->getClientOriginalExtension());
                $uploaded->move($imagesDir, $filename);
                // remove old image if exists
                if ($game->getImagePath()) {
                    $old = $this->getParameter('kernel.project_dir').'/public/'.ltrim($game->getImagePath(), '/');
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }
                $game->setImagePath('images/games/'.$filename);
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_game_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('game/edit.html.twig', [
            'game' => $game,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_game_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Game $game, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$game->getId(), $request->getPayload()->getString('_token'))) {
            $tournamentCount = $game->getTournaments()->count();

            if ($tournamentCount > 0) {
                $this->addFlash(
                    'error',
                    'No se puede eliminar el juego "'.$game->getName().'" porque tiene '.$tournamentCount.' torneo(s) asociado(s). Elimina primero los torneos o cámbiales el juego.'
                );

                return $this->redirectToRoute('app_game_index', [], Response::HTTP_SEE_OTHER);
            }

            $entityManager->remove($game);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_game_index', [], Response::HTTP_SEE_OTHER);
    }
}
