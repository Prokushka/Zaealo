<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\ImagePrompts\ImagePromptFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GenerateCardImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'scenarios' => ['required', 'array', 'min:1', 'max:5'],
            'scenarios.*.category' => ['required', 'string', Rule::in(['hero', 'lifestyle', 'features', 'infographics', 'packaging'])],
            'scenarios.*.subcategory' => ['required', 'string', 'max:100'],
            'infographic_features' => ['nullable', 'array'],
            'infographic_features.*' => ['required', 'string', 'max:120', 'distinct'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('scenarios', []) as $index => $scenario) {
                if (! is_array($scenario)) {
                    continue;
                }

                try {
                    ImagePromptFactory::make(
                        (string) ($scenario['category'] ?? ''),
                        (string) ($scenario['subcategory'] ?? ''),
                    )->generate();
                } catch (\InvalidArgumentException) {
                    $validator->errors()->add("scenarios.{$index}", 'Выбран недопустимый сценарий генерации.');
                }
            }
        }];
    }
}
