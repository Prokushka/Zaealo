<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class AiTunnelService
{
    public const EMBEDDING_DIMENSIONS = 1536;

    public function generateImage(string $prompt): GeneratedCardImage
    {
        if ($prompt === '') {
            throw new InvalidArgumentException('AITunnel image prompt cannot be empty.');
        }

        $response = $this->client()
            ->asJson()
            ->post('/v1/images/generations', [
                'model' => 'gpt-image-2',
                'prompt' => $prompt,
                'size' => '1024x1536',
                'quality' => 'medium',
                'output_format' => 'png',
            ])
            ->throw()
            ->json();

        return $this->generatedImageFromResponse($response);
    }

    public function editImage(string $prompt, string $referenceImage, string $filename): GeneratedCardImage
    {
        if ($prompt === '') {
            throw new InvalidArgumentException('AITunnel image prompt cannot be empty.');
        }

        if ($referenceImage === '') {
            throw new InvalidArgumentException('AITunnel reference image cannot be empty.');
        }

        $response = $this->client()
            ->attach('image[]', $referenceImage, $filename)
            ->post('/v1/images/edits', [
                'model' => 'gpt-image-2',
                'prompt' => $prompt,
                'input_fidelity' => 'high',
                'size' => '1024x1536',
                'quality' => 'medium',
                'output_format' => 'png',
            ])
            ->throw();

        return $this->generatedImageFromResponse($response->json());
    }

    /** @param array<string, mixed>|null $response */
    private function generatedImageFromResponse(?array $response): GeneratedCardImage
    {
        $encodedImage = data_get($response, 'data.0.b64_json');

        if (! is_string($encodedImage) || $encodedImage === '') {
            throw new RuntimeException('AITunnel did not return a generated image.');
        }

        $contents = base64_decode($encodedImage, true);

        if ($contents === false || $contents === '') {
            throw new RuntimeException('AITunnel returned an invalid generated image.');
        }

        if (! str_starts_with($contents, "\x89PNG\r\n\x1a\n")) {
            throw new RuntimeException('AITunnel did not return the requested PNG image.');
        }

        return new GeneratedCardImage($contents, 'png');
    }

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function chatCompletion(array $messages, AIChoice $model, array $options = []): array
    {
        if ($messages === []) {
            throw new InvalidArgumentException('AITunnel messages cannot be empty.');
        }

        return $this->sendChatCompletion([
            ...$model->model(),
            ...$options,
            'messages' => $messages,
        ]);
    }

    /**
     * @param  list<string>  $inputs
     * @return list<list<float>>
     */
    public function embeddings(array $inputs): array
    {
        if ($inputs === [] || collect($inputs)->contains(fn (mixed $input): bool => ! is_string($input) || $input === '')) {
            throw new InvalidArgumentException('AITunnel requires at least one non-empty embedding input.');
        }

        $response = $this->client()
            ->asJson()
            ->post('/v1/embeddings', [
                'model' => 'text-embedding-3-small',
                'input' => $inputs,
            ])
            ->throw()
            ->json();

        if (! is_array($response) || ! is_array($response['data'] ?? null)) {
            throw new RuntimeException('AITunnel returned an invalid embeddings response.');
        }

        $embeddings = [];

        foreach ($response['data'] as $item) {
            $index = is_array($item) ? ($item['index'] ?? null) : null;
            $embedding = is_array($item) ? ($item['embedding'] ?? null) : null;

            if (! is_int($index) || ! is_array($embedding) || count($embedding) !== self::EMBEDDING_DIMENSIONS) {
                throw new RuntimeException('AITunnel returned an invalid embedding vector.');
            }

            $vector = array_map(function (mixed $value): float {
                if (! is_numeric($value)) {
                    throw new RuntimeException('AITunnel returned a non-numeric embedding value.');
                }

                return (float) $value;
            }, $embedding);

            if (array_key_exists($index, $embeddings)) {
                throw new RuntimeException('AITunnel returned duplicate embedding indexes.');
            }

            $embeddings[$index] = $vector;
        }

        ksort($embeddings);

        if (array_keys($embeddings) !== array_keys($inputs)) {
            throw new RuntimeException('AITunnel returned embeddings in an incomplete order.');
        }

        return array_values($embeddings);
    }

    /**
     * @param  array<int, string>  $imageUrls
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function analyzeImages(
        string $prompt,
        array $imageUrls,
        AIChoice $model,
        array $options = [],
    ): array {
        if ($prompt === '') {
            throw new InvalidArgumentException('AITunnel image prompt cannot be empty.');
        }

        if ($imageUrls === [] || collect($imageUrls)->contains(fn (mixed $url): bool => ! is_string($url) || $url === '')) {
            throw new InvalidArgumentException('AITunnel requires at least one valid image URL.');
        }

        $content = [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
            ...array_map(
                fn (string $url): array => [
                    'type' => 'image_url',
                    'image_url' => ['url' => $url],
                ],
                $imageUrls,
            ),
        ];

        return $this->chatCompletion([
            ['role' => 'user', 'content' => $content],
        ], $model, $options);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendChatCompletion(array $payload): array
    {
        $response = $this->client()
            ->asJson()
            ->post('/v1/chat/completions', $payload)
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('AITunnel returned an invalid response.');
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->configuredValue('base_url'))
            ->acceptJson()
            ->withToken($this->configuredValue('key'))
            ->connectTimeout(5)
            ->timeout(120)
            ->retry([250, 750, 1500], throw: false);
    }

    private function configuredValue(string $key): string
    {
        $value = config("services.aitunnel.$key");

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("AITunnel [$key] is not configured.");
        }

        return $value;
    }
}
