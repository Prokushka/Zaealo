<?php

use App\Models\CardGeneration;
use App\Models\User;
use App\Services\AIChoice;
use App\Services\AiTunnelService;
use App\Services\CategoryMatcherService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

function fakeCardAnalysisPhoto(string $name, int $sizeInKilobytes = 1): UploadedFile
{
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );

    return UploadedFile::fake()->createWithContent(
        $name,
        $png.str_repeat("\0", $sizeInKilobytes * 1024),
    );
}

function fakeQuestionnaire(): array
{
    return [
        'product' => ['category' => 'Термокружка', 'confidence' => 'high'],
        'questions' => collect(range(1, 5))->map(fn (int $number): array => [
            'id' => "question_{$number}",
            'question' => "Вопрос {$number}?",
            'purpose' => $number <= 2 ? 'product_detail' : 'positioning',
            'type' => $number >= 4 ? 'multiple_choice' : 'single_choice',
            'required' => true,
            'options' => [
                ['id' => 'first', 'label' => 'Первый вариант'],
                ['id' => 'second', 'label' => 'Второй вариант'],
                ['id' => 'expert', 'label' => 'На усмотрение эксперта'],
            ],
        ])->all(),
    ];
}

function fakeS3ForCardAnalysis(): void
{
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path): string => "https://storage.test/{$path}",
    );
}

test('guests cannot upload photos for card analysis', function () {
    $this->post(route('card-analyses.store'))
        ->assertRedirect(route('login'));
});

test('quick analysis uses the cheap model without charging the user', function () {
    fakeS3ForCardAnalysis();
    $user = User::factory()->create(['balance' => 150]);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('analyzeImages')
        ->once()
        ->withArgs(fn (string $prompt, array $urls, AIChoice $model): bool => $model === AIChoice::QWEN_FLASH
            && str_contains($prompt, 'профессиональный коммерческий копирайтер')
            && str_contains($prompt, 'маркетплейсе Ozon')
            && str_contains($prompt, 'infographic_features')
            && str_contains($prompt, '2–4 слова')
            && count($urls) === 2)
        ->andReturn([
            'choices' => [['message' => ['content' => json_encode([
                'title' => 'Стильная термокружка',
                'description' => 'Удобная кружка для напитков в дороге.',
                'infographic_features' => ['Сохраняет тепло', 'Удобная крышка', 'Термостойкость'],
            ], JSON_UNESCAPED_UNICODE)]]],
        ]);
    app()->instance(AiTunnelService::class, $aiTunnel);
    $categoryMatcher = Mockery::mock(CategoryMatcherService::class);
    $categoryMatcher->shouldReceive('match')
        ->once()
        ->with('ozon', 'Стильная термокружка')
        ->andReturn([
            'recommended_id' => 101,
            'candidates' => [['description_category_id' => 101, 'type_id' => 202]],
        ]);
    app()->instance(CategoryMatcherService::class, $categoryMatcher);

    $response = $this
        ->actingAs($user)
        ->post(route('card-analyses.store'), [
            'marketplace' => 'ozon',
            'generation_mode' => 'quick',
            'copywriting_quality' => 'standard',
            'photos' => [
                fakeCardAnalysisPhoto('front.png', 1200),
                fakeCardAnalysisPhoto('side.png', 800),
            ],
        ]);

    $response->assertSessionHasNoErrors()
        ->assertSessionHas('card_analysis.status', 'completed')
        ->assertSessionHas('card_analysis.category_match.recommended_id', 101)
        ->assertSessionHas('card_analysis.title', 'Стильная термокружка')
        ->assertSessionHas('card_analysis.infographic_features.0', 'Сохраняет тепло')
        ->assertSessionHas('card_analysis.infographic_features.2', 'Термостойкость')
        ->assertRedirect();

    expect($user->refresh()->balance)->toBe(150)
        ->and($user->transactions()->count())->toBe(0)
        ->and(CardGeneration::query()->first()->cost_zarks)->toBe('0.00')
        ->and(CardGeneration::query()->first()->category_match)->toBe([
            'recommended_id' => 101,
            'candidates' => [['description_category_id' => 101, 'type_id' => 202]],
        ]);
});

test('quick pro analysis uses sonnet and charges fifty zarks', function () {
    fakeS3ForCardAnalysis();
    $user = User::factory()->create(['balance' => 150]);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('analyzeImages')
        ->once()
        ->withArgs(fn (string $prompt, array $urls, AIChoice $model): bool => $model === AIChoice::Sonnet)
        ->andReturn([
            'choices' => [['message' => ['content' => '{"title":"PRO название","description":"PRO описание","infographic_features":["Премиальный дизайн","Удобная форма"]}']]],
        ]);
    app()->instance(AiTunnelService::class, $aiTunnel);

    $this->actingAs($user)->post(route('card-analyses.store'), [
        'marketplace' => 'wildberries',
        'generation_mode' => 'quick',
        'copywriting_quality' => 'pro',
        'photos' => [fakeCardAnalysisPhoto('front.png')],
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->balance)->toBe(100)
        ->and($user->transactions()->where('type', 'debit')->value('amount'))->toBe('-50.00')
        ->and(CardGeneration::query()->first()->cost_zarks)->toBe('50.00');
});

test('pro analysis is rejected without enough zarks', function () {
    fakeS3ForCardAnalysis();
    $user = User::factory()->create(['balance' => 30]);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldNotReceive('analyzeImages');
    app()->instance(AiTunnelService::class, $aiTunnel);

    $this->actingAs($user)->post(route('card-analyses.store'), [
        'marketplace' => 'wildberries',
        'generation_mode' => 'quick',
        'copywriting_quality' => 'pro',
        'photos' => [fakeCardAnalysisPhoto('front.png')],
    ])->assertSessionHasErrors('copywriting_quality');

    expect($user->refresh()->balance)->toBe(30)
        ->and($user->transactions()->count())->toBe(0)
        ->and(CardGeneration::query()->count())->toBe(0);
});

test('standard analysis builds a questionnaire with gpt mini and generates copy after answers', function () {
    fakeS3ForCardAnalysis();
    $user = User::factory()->create(['balance' => 150]);
    $questionnaire = fakeQuestionnaire();
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('analyzeImages')
        ->once()
        ->withArgs(fn (string $prompt, array $urls, AIChoice $model): bool => $model === AIChoice::GPT4MINI
            && str_contains($prompt, 'стратег по позиционированию'))
        ->andReturn([
            'choices' => [['message' => ['content' => json_encode($questionnaire, JSON_UNESCAPED_UNICODE)]]],
        ]);
    $aiTunnel->shouldReceive('analyzeImages')
        ->once()
        ->withArgs(fn (string $prompt, array $urls, AIChoice $model): bool => $model === AIChoice::Sonnet
            && str_contains($prompt, 'Первый вариант')
            && str_contains($prompt, 'infographic_features'))
        ->andReturn([
            'choices' => [['message' => ['content' => '{"title":"Термокружка для города","description":"Продуманное описание.","infographic_features":["Удобно брать","Сохраняет тепло"]}']]],
        ]);
    app()->instance(AiTunnelService::class, $aiTunnel);

    $response = $this->actingAs($user)->post(route('card-analyses.store'), [
        'marketplace' => 'ozon',
        'generation_mode' => 'standard',
        'copywriting_quality' => 'pro',
        'photos' => [fakeCardAnalysisPhoto('front.png')],
    ]);

    $generation = CardGeneration::query()->firstOrFail();
    $response->assertSessionHas('card_analysis.status', 'questionnaire');
    expect($user->refresh()->balance)->toBe(150)
        ->and($generation->status)->toBe('awaiting_answers');

    $answers = collect($questionnaire['questions'])
        ->mapWithKeys(fn (array $question): array => [
            $question['id'] => $question['type'] === 'multiple_choice' ? ['first', 'second'] : 'first',
        ])->all();

    $this->actingAs($user)
        ->post(route('card-analyses.complete', $generation), ['answers' => $answers])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('card_analysis.status', 'completed')
        ->assertSessionHas('card_analysis.title', 'Термокружка для города')
        ->assertSessionHas('card_analysis.infographic_features.0', 'Удобно брать');

    expect($user->refresh()->balance)->toBe(100)
        ->and($generation->refresh()->status)->toBe('completed')
        ->and($generation->generated_bullets)->toBe(['Удобно брать', 'Сохраняет тепло'])
        ->and($generation->card->refresh()->title)->toBe('Термокружка для города');
});

test('standard analysis accepts only the relevant questionnaire questions from the model', function () {
    fakeS3ForCardAnalysis();
    $user = User::factory()->create();
    $questionnaire = fakeQuestionnaire();
    $questionnaire['questions'] = collect($questionnaire['questions'])
        ->take(1)
        ->map(fn (array $question): array => Arr::except($question, ['purpose']))
        ->all();
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('analyzeImages')->once()->andReturn([
        'choices' => [['message' => ['content' => json_encode($questionnaire, JSON_UNESCAPED_UNICODE)]]],
    ]);
    app()->instance(AiTunnelService::class, $aiTunnel);

    $this->actingAs($user)->post(route('card-analyses.store'), [
        'marketplace' => 'ozon',
        'generation_mode' => 'standard',
        'copywriting_quality' => 'standard',
        'photos' => [fakeCardAnalysisPhoto('front.png')],
    ])->assertSessionHasNoErrors()
        ->assertSessionHas('card_analysis.status', 'questionnaire')
        ->assertSessionHas('card_analysis.questionnaire.questions.0.purpose', 'positioning');
});

test('card analysis requires supported options and photos', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('card-analyses.store'), [
            'marketplace' => 'unsupported',
            'generation_mode' => 'standard',
            'copywriting_quality' => 'ultra',
            'photos' => [],
        ])
        ->assertSessionHasErrors(['marketplace', 'copywriting_quality', 'photos']);
});

test('card analysis accepts no more than five photos', function () {
    $user = User::factory()->create();
    $photos = collect(range(1, 6))
        ->map(fn (int $number): UploadedFile => fakeCardAnalysisPhoto("photo-{$number}.png"))
        ->all();

    $this
        ->actingAs($user)
        ->post(route('card-analyses.store'), [
            'marketplace' => 'wildberries',
            'generation_mode' => 'quick',
            'copywriting_quality' => 'standard',
            'photos' => $photos,
        ])
        ->assertSessionHasErrors('photos');
});

test('card analysis limits the combined photo size to five megabytes', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('card-analyses.store'), [
            'marketplace' => 'wildberries',
            'generation_mode' => 'quick',
            'copywriting_quality' => 'standard',
            'photos' => [
                fakeCardAnalysisPhoto('first.png', 3000),
                fakeCardAnalysisPhoto('second.png', 2500),
            ],
        ])
        ->assertSessionHasErrors('photos');
});
