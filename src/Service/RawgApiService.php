<?php

namespace App\Service;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RawgApiService
{
    public function __construct(
        #[Autowire('%env(string:RAWG_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function searchGames(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $payload = $this->requestJson('games', [
            'search' => $query,
            'page_size' => 6,
        ]);

        return array_map(
            fn (array $item): array => $this->normalizeGame($item),
            $payload['results'] ?? []
        );
    }

    public function getGame(int $rawgId): array
    {
        $payload = $this->requestJson('games/'.$rawgId);

        return $this->normalizeGame($payload);
    }

    public function downloadImage(string $imageUrl, string $gameName): string
    {
        $imageUrl = trim($imageUrl);

        if ($imageUrl === '') {
            return '';
        }

        $slug = $this->slugify($gameName);
        $extension = strtolower(pathinfo((string) parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
        $relativePath = 'images/games/'.$slug.'.'.$extension;
        $absolutePath = $this->projectDir.'/public/'.$relativePath;

        if (is_file($absolutePath)) {
            return $relativePath;
        }

        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio de imágenes.');
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $contents = @file_get_contents($imageUrl, false, $context);
        if ($contents === false) {
            throw new RuntimeException('No se pudo descargar la imagen de RAWG.');
        }

        if (file_put_contents($absolutePath, $contents) === false) {
            throw new RuntimeException('No se pudo guardar la imagen de RAWG.');
        }

        return $relativePath;
    }

    private function requestJson(string $path, array $query = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('RAWG_API_KEY no está configurada.');
        }

        $query = array_merge(['key' => $this->apiKey], $query);
        $url = 'https://api.rawg.io/api/'.ltrim($path, '/').'?'.http_build_query($query);
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('No se pudo conectar con RAWG.');
        }

        $statusCode = $this->extractStatusCode($http_response_header ?? []);
        if ($statusCode >= 400) {
            throw new RuntimeException('RAWG respondió con un error HTTP '.$statusCode.'.');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('RAWG devolvió una respuesta inválida.');
        }

        return $decoded;
    }

    private function extractStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 200;
    }

    private function normalizeGame(array $item): array
    {
        $genres = [];
        foreach ($item['genres'] ?? [] as $genre) {
            if (is_array($genre) && isset($genre['name'])) {
                $genres[] = (string) $genre['name'];
            }
        }

        return [
            'id' => (int) ($item['id'] ?? 0),
            'name' => (string) ($item['name'] ?? ''),
            'background_image' => $item['background_image'] ?? null,
            'genres' => $genres,
            'description_raw' => (string) ($item['description_raw'] ?? ''),
        ];
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value !== '' ? $value : 'game';
    }
}