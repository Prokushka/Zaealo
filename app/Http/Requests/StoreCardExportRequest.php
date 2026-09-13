<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CardGeneration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCardExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:20000'],
            'category_id' => ['required', 'integer', 'min:1'],
            'type_id' => ['nullable', 'integer', 'min:1'],
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

            if ($generation->card->marketplace === 'ozon' && $this->integer('type_id') <= 0) {
                $validator->errors()->add('type_id', 'Выберите тип товара Ozon.');
            }

            if ($generation->card->marketplace === 'wildberries' && mb_strlen($this->string('title')->value()) > 60) {
                $validator->errors()->add('title', 'Название для Wildberries должно быть не длиннее 60 символов.');
            }
        }];
    }
}
