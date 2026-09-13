<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AnalyzeCardRequest extends FormRequest
{
    private const MAXIMUM_TOTAL_PHOTO_SIZE_IN_BYTES = 5 * 1024 * 1024;

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
            'marketplace' => ['required', 'string', Rule::in(['ozon', 'wildberries'])],
            'generation_mode' => ['required', 'string', Rule::in(['quick', 'standard'])],
            'copywriting_quality' => ['required', 'string', Rule::in(['standard', 'pro'])],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => [
                'required',
                'file',
                'image',
                Rule::when(
                    $this->input('marketplace') === 'ozon',
                    ['mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png'],
                    ['mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp'],
                ),
                'max:5120',
            ],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $totalPhotoSize = collect($this->file('photos', []))
                    ->sum(fn (UploadedFile $photo): int => $photo->getSize());

                if ($totalPhotoSize > self::MAXIMUM_TOTAL_PHOTO_SIZE_IN_BYTES) {
                    $validator->errors()->add(
                        'photos',
                        'Общий размер фотографий не должен превышать 5 МБ.',
                    );
                }
            },
        ];
    }
}
