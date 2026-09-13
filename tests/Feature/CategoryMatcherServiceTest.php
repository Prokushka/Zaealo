<?php

use App\Models\OzonCategory;
use App\Models\WbCategory;
use App\Services\AIChoice;
use App\Services\AiTunnelService;
use App\Services\CategoryMatcherService;

function matcherEmbedding(float $firstValue, float $secondValue = 0.0): array
{
    return [$firstValue, $secondValue, ...array_fill(0, AiTunnelService::EMBEDDING_DIMENSIONS - 2, 0.0)];
}

test('matches Ozon categories by title and retains the type ID in candidates', function (): void {
    OzonCategory::query()->create([
        'description_category_id' => 10,
        'type_id' => 100,
        'category_name' => 'Головные уборы',
        'type_name' => 'Кепки',
        'full_path' => 'Одежда > Головные уборы > Кепки',
        'embedding' => matcherEmbedding(1.0),
    ]);
    OzonCategory::query()->create([
        'description_category_id' => 11,
        'type_id' => 101,
        'category_name' => 'Головные уборы',
        'type_name' => 'Шапки',
        'full_path' => 'Одежда > Головные уборы > Шапки',
        'embedding' => matcherEmbedding(0.0, 1.0),
    ]);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('embeddings')
        ->once()
        ->with(['Красная кепка'])
        ->andReturn([matcherEmbedding(1.0)]);
    $aiTunnel->shouldReceive('chatCompletion')
        ->once()
        ->withArgs(fn (array $messages, AIChoice $model): bool => $model === AIChoice::GPT4MINI
            && str_contains($messages[1]['content'], 'Красная кепка'))
        ->andReturn([
            'choices' => [['message' => ['content' => '{"recommended_id":"10"}']]],
        ]);

    $result = (new CategoryMatcherService($aiTunnel))->match('ozon', '  Красная   кепка  ');

    expect($result['recommended_id'])->toBe(10)
        ->and($result['candidates'])->toHaveCount(2)
        ->and($result['candidates'][0])->toMatchArray([
            'description_category_id' => 10,
            'type_id' => 100,
            'full_path' => 'Одежда > Головные уборы > Кепки',
        ]);
});

test('uses the nearest category when an LLM selects an ID outside the candidates', function (): void {
    WbCategory::query()->create([
        'subject_id' => 100,
        'subject_name' => 'Кепки',
        'parent_name' => 'Одежда',
        'full_path' => 'Одежда > Кепки',
        'embedding' => matcherEmbedding(1.0),
    ]);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('embeddings')->once()->andReturn([matcherEmbedding(1.0)]);
    $aiTunnel->shouldReceive('chatCompletion')->once()->andReturn([
        'choices' => [['message' => ['content' => '{"recommended_id":999}']]],
    ]);

    $result = (new CategoryMatcherService($aiTunnel))->match('wb', 'Кепка');

    expect($result['recommended_id'])->toBe(100)
        ->and($result['candidates'][0]['subject_id'])->toBe(100);
});

test('does not call AI when the catalogue is empty', function (): void {
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldNotReceive('embeddings');
    $aiTunnel->shouldNotReceive('chatCompletion');

    expect((new CategoryMatcherService($aiTunnel))->match('ozon', 'Кепка'))->toBe([
        'recommended_id' => null,
        'candidates' => [],
    ]);
});
