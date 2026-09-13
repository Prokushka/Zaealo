<?php

namespace App\Http\Requests;

use App\Models\CardGeneration;
use App\Models\OzonCategory;
use App\Models\WbCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FillCardAttributesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'min:1'],
            'type_id' => ['nullable', 'integer', 'min:1'],
            'donor_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $generation = $this->route('generation');

            if (! $generation instanceof CardGeneration) {
                return;
            }

            $generation->loadMissing('card');
            $categoryId = $this->integer('category_id');

            if ($generation->card->marketplace === 'ozon') {
                $typeId = $this->integer('type_id');

                if ($typeId <= 0) {
                    $validator->errors()->add('type_id', 'Выберите тип товара Ozon.');

                    return;
                }

                if (! OzonCategory::query()
                    ->where('description_category_id', $categoryId)
                    ->where('type_id', $typeId)
                    ->exists()) {
                    $validator->errors()->add('category_id', 'Выбранная категория Ozon недоступна.');
                }

                return;
            }

            if (! WbCategory::query()->where('subject_id', $categoryId)->exists()) {
                $validator->errors()->add('category_id', 'Выбранный предмет Wildberries недоступен.');
            }
        }];
    }
}
