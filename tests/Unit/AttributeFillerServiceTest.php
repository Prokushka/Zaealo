<?php

use App\Exceptions\AttributeFillingException;
use App\Models\Card;
use App\Models\CardGeneration;
use App\Models\CardImage;
use App\Services\AIChoice;
use App\Services\AiTunnelService;
use App\Services\AttributeFillerService;
use App\Services\MarketplaceApiService;
use App\Services\MarketplaceProductSearchService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('fills attributes from a donor without invoking AI', function (): void {
    $productSearch = Mockery::mock(MarketplaceProductSearchService::class);
    $productSearch->shouldReceive('fetchDonorAttributes')
        ->once()
        ->with('wildberries', '123456')
        ->andReturn([
            'Материал' => 'Сталь',
            'brand' => 'Не сохранять',
            'PRICE' => 1990,
            'Наименование' => 'Не сохранять',
            'Описание' => 'Не сохранять',
            'Размер' => 'Не указано',
            'Цвет' => 'Чёрный',
            'Пустое поле' => ' ',
            'Пустой список' => [],
        ]);
    $marketplaceApi = Mockery::mock(MarketplaceApiService::class);
    $marketplaceApi->shouldNotReceive('attributeSchema');
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldNotReceive('analyzeImages');
    $generation = Mockery::mock(CardGeneration::class)->makePartial();
    $generation->setRelation('card', new Card(['marketplace' => 'wildberries']));
    $generation->shouldReceive('loadMissing')->once()->with('card')->andReturnSelf();
    $generation->shouldReceive('update')->once()->with([
        'attributes_data' => [
            'Материал' => 'Сталь',
            'brand' => 'Не сохранять',
            'PRICE' => 1990,
            'Наименование' => 'Не сохранять',
            'Описание' => 'Не сохранять',
            'Размер' => 'Не указано',
            'Цвет' => 'Чёрный',
        ],
    ]);

    $attributes = (new AttributeFillerService($productSearch, $marketplaceApi, $aiTunnel))
        ->fill($generation, '123456');

    expect($attributes)->toBe([
        'Материал' => 'Сталь',
        'brand' => 'Не сохранять',
        'PRICE' => 1990,
        'Наименование' => 'Не сохранять',
        'Описание' => 'Не сохранять',
        'Размер' => 'Не указано',
        'Цвет' => 'Чёрный',
    ]);
});

test('fills AI attributes using marketplace rules', function (string $marketplace, string $content, ?array $expected): void {
    $schema = [
        ['key' => 'Материал', 'required' => false, 'dictionary_id' => 10, 'is_collection' => false],
        ['key' => 'ТН ВЭД коды ЕАЭС', 'required' => true, 'dictionary_id' => 20, 'is_collection' => false],
        ['key' => 'Особенности', 'required' => false, 'dictionary_id' => 30, 'is_collection' => true],
        ['key' => 'Хэштеги', 'required' => false, 'dictionary_id' => 0, 'is_collection' => false],
        ['key' => 'Название PDF-файла', 'required' => false, 'dictionary_id' => 0, 'is_collection' => false],
        ['key' => 'Видео', 'required' => false, 'dictionary_id' => 0, 'is_collection' => false],
        ['key' => 'Название', 'required' => false, 'dictionary_id' => 0, 'is_collection' => false],
        ['key' => 'Озон.Видео: название', 'required' => false, 'dictionary_id' => 0, 'is_collection' => false],
        ['key' => 'Озон.Видеообложка: ссылка', 'required' => false, 'dictionary_id' => 0, 'is_collection' => false],
        ['key' => 'Количество товара в УЕИ', 'required' => false, 'dictionary_id' => 0, 'is_collection' => false],
        ['key' => 'Объединить в похожие товары', 'required' => false, 'dictionary_id' => 0, 'is_collection' => false],
    ];
    $generation = Mockery::mock(CardGeneration::class)->makePartial();
    $generation->setRelation('card', new Card([
        'marketplace' => $marketplace,
        'title' => 'Защитная каска',
        'description' => 'Описание товара',
    ]));
    $generation->shouldReceive('loadMissing')->once()->with('card')->andReturnSelf();
    $images = Mockery::mock(HasMany::class);
    $images->shouldReceive('where')->once()->with('type', CardImage::TYPE_USER_UPLOAD)->andReturnSelf();
    $images->shouldReceive('orderByDesc')->once()->with('is_main')->andReturnSelf();
    $images->shouldReceive('oldest')->once()->with('id')->andReturnSelf();
    $images->shouldReceive('first')->once()->andReturn(new CardImage(['path' => 'cards/photo.jpg']));
    $generation->shouldReceive('images')->once()->andReturn($images);
    Storage::shouldReceive('disk')->once()->with('s3')->andReturnSelf();
    Storage::shouldReceive('temporaryUrl')->once()->with('cards/photo.jpg', Mockery::any())
        ->andReturn('https://storage.test/photo.jpg');

    $productSearch = Mockery::mock(MarketplaceProductSearchService::class);
    $productSearch->shouldNotReceive('fetchDonorAttributes');
    $marketplaceApi = Mockery::mock(MarketplaceApiService::class);
    $marketplaceApi->shouldReceive('attributeSchema')->once()->with($generation)->andReturn($schema);
    $aiTunnel = Mockery::mock(AiTunnelService::class);
    $aiTunnel->shouldReceive('analyzeImages')->once()->with(
        Mockery::on(function (string $prompt) use ($marketplace, $schema): bool {
            [$instruction, $context] = explode("\n\n{", $prompt, 2);
            expect(json_decode('{'.$context, true, flags: JSON_THROW_ON_ERROR))->toBe([
                'title' => 'Защитная каска',
                'description' => 'Описание товара',
                'schema' => $schema,
            ]);

            if ($marketplace === 'ozon') {
                expect($instruction)
                    ->toContain('заполни как можно больше характеристик Ozon из schema')
                    ->toContain('цвет определяй по фото')
                    ->toContain('вес и размеры реалистично оценивай')
                    ->toContain('Поле с dictionary_id > 0 заполняй только известным стандартным значением Ozon')
                    ->toContain('ТН ВЭД возвращай только строкой из 10 цифр')
                    ->toContain('Бренд самостоятельно не заполняй')
                    ->toContain('Название и описание уже созданы на SEO-этапе')
                    ->toContain('Если schema содержит хэштеги, обязательно заполни их')
                    ->toContain('Верни только JSON-объект с заполненными характеристиками')
                    ->not->toContain('Название PDF-файла')
                    ->not->toContain('Последовательно проверь каждое поле schema');
            } else {
                expect($instruction)
                    ->toContain('сделай реалистичную оценку по фото')
                    ->not->toContain('Ozon')
                    ->not->toContain('dictionary_id');
            }

            return true;
        }),
        ['https://storage.test/photo.jpg'],
        AIChoice::GPT4MINI,
        ['max_tokens' => 4096],
    )->andReturn(['choices' => [['message' => ['content' => $content]]]]);

    $service = new AttributeFillerService($productSearch, $marketplaceApi, $aiTunnel);

    if ($expected === null) {
        $generation->shouldNotReceive('update');
        expect(fn () => $service->fill($generation))->toThrow(AttributeFillingException::class);
    } else {
        $generation->shouldReceive('update')->once()->with(['attributes_data' => $expected]);
        expect($service->fill($generation))->toBe($expected);
    }
})->with([
    'empty Ozon object has no inputs' => ['ozon', '{}', []],
    'fenced empty Ozon object' => ['ozon', "```json\n{ \n }\n```", []],
    'partial Ozon attributes have no missing fields' => ['ozon', '{"Материал":"Пластик"}', ['Материал' => 'Пластик']],
    'Ozon preserves filled codes collections boolean and zero' => ['ozon', '{"ТН ВЭД коды ЕАЭС":"6506101000","Особенности":["Вентиляция","Регулировка"],"Хэштеги":"#каска #защита","Название":null,"Название PDF-файла":"","Количество товара в УЕИ":0,"Объединить в похожие товары":false}', ['ТН ВЭД коды ЕАЭС' => '6506101000', 'Особенности' => ['Вентиляция', 'Регулировка'], 'Хэштеги' => '#каска #защита', 'Количество товара в УЕИ' => 0, 'Объединить в похожие товары' => false]],
    'Ozon rejects empty list' => ['ozon', '[]', null],
    'Ozon rejects nonempty list' => ['ozon', '["Пластик"]', null],
    'Ozon rejects null' => ['ozon', 'null', null],
    'Ozon rejects malformed JSON' => ['ozon', '{', null],
    'Ozon drops unknown keys' => ['ozon', '{"Выдуманное поле":"значение"}', []],
    'WB keeps its prompt and partial response' => ['wildberries', '{"Материал":"Пластик"}', ['Материал' => 'Пластик']],
    'WB still rejects empty object' => ['wildberries', '{}', null],
]);
