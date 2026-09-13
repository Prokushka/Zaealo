<?php

use App\Services\PromptEngineer;

it('builds a fast mode prompt for json product copy', function () {
    $prompt = app(PromptEngineer::class)->fastModePrompt('ozon');

    expect($prompt)
        ->toContain('профессиональный коммерческий копирайтер')
        ->toContain('Не выдумывай бренд')
        ->toContain('infographic_features')
        ->toContain('маркетплейсе Ozon')
        ->toContain('Пиши непосредственно для покупателя')
        ->toContain('общеупотребимую категорию и тип товара')
        ->toContain('"title"')
        ->toContain('"description"')
        ->toContain('2–4 слова');
});

it('builds a choice only positioning questionnaire with a strict schema', function () {
    $prompt = app(PromptEngineer::class)->prePrompt();

    expect($prompt)
        ->toContain('Верни исключительно валидный JSON')
        ->toContain('"questions"')
        ->toContain('single_choice|multiple_choice')
        ->toContain('Самостоятельно определи нужное количество вопросов')
        ->toContain('только действительно важные пробелы')
        ->toContain('Не углубляйся в технические микро-детали')
        ->toContain('если вопросов не нужно, верни пустой массив')
        ->not->toContain('text|textarea|number');
});

it('embeds questionnaire data into the default mode prompt as unicode json', function () {
    $prompt = app(PromptEngineer::class)->defaultModePrompt(
        [
            'product' => ['category' => 'Термокружка'],
            'answers' => ['volume' => '500 мл'],
        ],
        'wildberries',
    );

    expect($prompt)
        ->toContain('<questionnaire_data>')
        ->toContain('"category":"Термокружка"')
        ->toContain('"volume":"500 мл"')
        ->toContain('маркетплейсе Wildberries')
        ->toContain('не является ответом продавцу')
        ->toContain('не упоминай фотографии, анкету')
        ->toContain('infographic_features')
        ->toContain('"title"')
        ->toContain('"description"');
});

it('accepts an already serialized questionnaire', function () {
    $questionnaire = '{"material":"steel"}';

    expect(app(PromptEngineer::class)->defaultModePrompt($questionnaire, 'ozon'))
        ->toContain($questionnaire);
});
