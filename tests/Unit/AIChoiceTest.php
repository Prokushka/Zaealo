<?php

use App\Services\AIChoice;

it('provides numeric sampling parameters', function (AIChoice $choice) {
    $parameters = $choice->model();

    expect($parameters['temperature'])->toBeFloat()
        ->and($parameters['top_p'])->toBeFloat()
        ->and($parameters['frequency_penalty'])->toBeFloat()
        ->and($parameters['presence_penalty'])->toBeFloat();
})->with([
    'sonnet' => AIChoice::Sonnet,
    'gpt 4o mini' => AIChoice::GPT4MINI,
    'qwen flash' => AIChoice::QWEN_FLASH,
]);
