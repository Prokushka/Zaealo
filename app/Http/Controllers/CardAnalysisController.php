<?php

namespace App\Http\Controllers;

use App\Data\GeneratedProductCopyData;
use App\Enums\PricingKey;
use App\Exceptions\InsufficientZarks;
use App\Http\Requests\AnalyzeCardRequest;
use App\Http\Requests\CompleteCardAnalysisRequest;
use App\Models\CardGeneration;
use App\Models\User;
use App\Services\AIChoice;
use App\Services\AiTunnelService;
use App\Services\CategoryMatcherService;
use App\Services\PricingCatalog;
use App\Services\PromptEngineer;
use App\Services\ZarkWallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CardAnalysisController extends Controller
{
    public function __construct(
        private AiTunnelService $aiTunnel,
        private CategoryMatcherService $categoryMatcher,
        private PromptEngineer $promptEngineer,
        private ZarkWallet $wallet,
        private PricingCatalog $pricing,
    ) {}

    public function store(AnalyzeCardRequest $request): RedirectResponse
    {
        $card = null;
        $analysisId = (string) Str::uuid();
        $analysisDirectory = "card-analyses/users/{$request->user()->getAuthIdentifier()}/{$analysisId}";
        $paths = collect($request->file('photos'))
            ->map(fn (UploadedFile $photo): string|false => $photo->store($analysisDirectory, 's3'))
            ->map(function (string|false $path): string {
                if ($path === false) {
                    throw new RuntimeException('Не удалось сохранить фотографию товара.');
                }

                return $path;
            })
            ->all();

        try {
            [$card, $generation] = DB::transaction(function () use ($request, $paths): array {
                $card = $request->user()->cards()->create([
                    'marketplace' => $request->validated('marketplace'),
                    'title' => '',
                    'status' => 'processing',
                ]);
                $generation = $card->generations()->create([
                    'mode' => $request->validated('generation_mode'),
                    'selected_style' => $request->validated('copywriting_quality'),
                    'status' => 'processing',
                ]);

                foreach ($paths as $index => $path) {
                    $card->images()->create([
                        'generation_id' => $generation->getKey(),
                        'path' => $path,
                        'is_main' => $index === 0,
                    ]);
                }

                return [$card, $generation];
            });

            $imageUrls = $this->temporaryImageUrls($paths);

            if ($request->validated('generation_mode') === 'quick') {
                $result = $this->generateCopy(
                    $generation,
                    $request->user(),
                    $this->promptEngineer->fastModePrompt($request->validated('marketplace')),
                    $imageUrls,
                );

                return back()->with('card_analysis', [
                    'status' => 'completed',
                    'analysis_id' => $generation->getKey(),
                    ...$result->toArray(),
                    'category_match' => $generation->refresh()->category_match,
                ]);
            }

            $questionnaire = $this->parseQuestionnaire(
                $this->aiTunnel->analyzeImages(
                    $this->promptEngineer->prePrompt(),
                    $imageUrls,
                    AIChoice::GPT4MINI,
                ),
            );

            $generation->update([
                'prompt_input' => json_encode($questionnaire, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'status' => 'awaiting_answers',
            ]);

            return back()->with('card_analysis', [
                'status' => 'questionnaire',
                'analysis_id' => $generation->getKey(),
                'questionnaire' => $questionnaire,
            ]);
        } catch (Throwable $exception) {
            $card?->delete();
            Storage::disk('s3')->deleteDirectory($analysisDirectory);

            throw $exception;
        }
    }

    public function complete(CompleteCardAnalysisRequest $request, CardGeneration $generation): RedirectResponse
    {
        $generation->loadMissing(['card.images']);
        abort_unless($generation->card->user_id === $request->user()->getAuthIdentifier(), 404);

        if ($generation->mode !== 'standard' || $generation->status !== 'awaiting_answers') {
            throw ValidationException::withMessages(['analysis' => 'Этот опрос уже обработан или недоступен.']);
        }

        $questionnaire = $this->decodeStoredQuestionnaire($generation);
        $answers = $this->validatedQuestionnaireAnswers($questionnaire, $request->validated('answers'));
        $promptData = ['product' => $questionnaire['product'], 'answers' => $answers];

        DB::transaction(function () use ($generation): void {
            $lockedGeneration = CardGeneration::query()->lockForUpdate()->findOrFail($generation->getKey());

            if ($lockedGeneration->status !== 'awaiting_answers') {
                throw ValidationException::withMessages(['analysis' => 'Этот опрос уже обрабатывается.']);
            }

            $lockedGeneration->update(['status' => 'processing']);
        });

        $result = $this->generateCopy(
            $generation,
            $request->user(),
            $this->promptEngineer->defaultModePrompt($promptData, $generation->card->marketplace),
            $this->temporaryImageUrls($generation->card->images->pluck('path')->all()),
            $promptData,
        );

        return back()->with('card_analysis', [
            'status' => 'completed',
            'analysis_id' => $generation->getKey(),
            ...$result->toArray(),
            'category_match' => $generation->refresh()->category_match,
        ]);
    }

    /**
     * @param  array<int, string>  $imageUrls
     * @param  array<string, mixed>|null  $promptData
     */
    private function generateCopy(CardGeneration $generation, User $user, string $prompt, array $imageUrls, ?array $promptData = null): GeneratedProductCopyData
    {
        $model = $generation->selected_style === 'pro' ? AIChoice::Sonnet : AIChoice::QWEN_FLASH;
        $cost = $model === AIChoice::Sonnet ? $this->pricing->cost(PricingKey::ProCopywriting) : 0;
        $debited = false;

        if ($cost > 0) {
            try {
                $this->wallet->debit(
                    $user,
                    $cost,
                    'PRO-копирайтинг товарной карточки',
                    $generation,
                );
                $debited = true;
            } catch (InsufficientZarks $exception) {
                if ($generation->mode === 'standard') {
                    $generation->update(['status' => 'awaiting_answers']);
                }

                throw ValidationException::withMessages([
                    'copywriting_quality' => "Недостаточно ZARQ: PRO-копирайтинг стоит {$cost}, на балансе {$exception->available}. Выберите обычное качество или пополните баланс.",
                ]);
            }
        }

        try {
            $result = $this->parseGeneratedCopy($this->aiTunnel->analyzeImages($prompt, $imageUrls, $model));

            DB::transaction(function () use ($generation, $result, $promptData, $cost): void {
                $generation->update([
                    'prompt_input' => $promptData === null ? null : json_encode($promptData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'generated_title' => $result->title,
                    'generated_description' => $result->description,
                    'generated_bullets' => $result->infographicFeatures,
                    'status' => 'completed',
                    'cost_zarks' => $cost,
                ]);
                $generation->card()->update([
                    'title' => $result->title,
                    'description' => $result->description,
                    'status' => 'ready',
                ]);
            });

            $this->matchGeneratedCategory($generation, $result->title);

            return $result;
        } catch (Throwable $exception) {
            if ($debited) {
                $this->wallet->refund($generation, 'Возврат за неудачную генерацию товарной карточки');
            }

            $generation->update(['status' => 'failed', 'cost_zarks' => 0]);
            $generation->card()->update(['status' => 'draft']);

            throw $exception;
        }
    }

    private function matchGeneratedCategory(CardGeneration $generation, string $title): void
    {
        try {
            $marketplace = $generation->card()->value('marketplace');

            if (! is_string($marketplace)) {
                return;
            }

            $match = $this->categoryMatcher->match(
                $marketplace === 'wildberries' ? 'wb' : $marketplace,
                $title,
            );

            $generation->update([
                'category_match' => $match['candidates'] === [] ? null : $match,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unable to match generated card category.', [
                'generation_id' => $generation->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /** @param array<int, string> $paths */
    private function temporaryImageUrls(array $paths): array
    {
        return collect($paths)
            ->map(fn (string $path): string => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(15)))
            ->all();
    }

    /** @param array<string, mixed> $response */
    private function parseGeneratedCopy(array $response): GeneratedProductCopyData
    {
        $validated = Validator::make($this->decodeAssistantJson($response), [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:20000'],
            'infographic_features' => ['required', 'array', 'min:1'],
            'infographic_features.*' => ['required', 'string', 'max:120', 'distinct'],
        ])->validate();

        return GeneratedProductCopyData::fromValidated($validated);
    }

    /** @param array<string, mixed> $response */
    private function parseQuestionnaire(array $response): array
    {
        $questionnaire = $this->normalizeQuestionnaire($this->decodeAssistantJson($response));

        return Validator::make($questionnaire, [
            'product' => ['required', 'array:category,confidence'],
            'product.category' => ['nullable', 'string'],
            'product.confidence' => ['required', 'string', 'in:high,medium,low'],
            'questions' => ['required', 'array'],
            'questions.*' => ['required', 'array:id,question,purpose,type,required,options'],
            'questions.*.id' => ['required', 'string', 'distinct'],
            'questions.*.question' => ['required', 'string'],
            'questions.*.purpose' => ['required', 'string', 'in:product_detail,positioning'],
            'questions.*.type' => ['required', 'string', 'in:single_choice,multiple_choice'],
            'questions.*.required' => ['required', 'boolean', 'accepted'],
            'questions.*.options' => ['required', 'array', 'min:2'],
            'questions.*.options.*' => ['required', 'array:id,label'],
            'questions.*.options.*.id' => ['required', 'string'],
            'questions.*.options.*.label' => ['required', 'string'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $questionnaire
     * @return array<string, mixed>
     */
    private function normalizeQuestionnaire(array $questionnaire): array
    {
        $product = Arr::get($questionnaire, 'product', []);
        $questions = collect(Arr::get($questionnaire, 'questions', []))
            ->filter(fn (mixed $question): bool => is_array($question))
            ->values()
            ->map(function (array $question): array {
                $options = collect(Arr::get($question, 'options', []))
                    ->filter(fn (mixed $option): bool => is_array($option))
                    ->map(fn (array $option): array => Arr::only($option, ['id', 'label']))
                    ->values()
                    ->all();

                return [
                    ...Arr::only($question, ['id', 'question', 'type']),
                    'purpose' => Arr::get(
                        $question,
                        'purpose',
                        'positioning',
                    ),
                    'required' => true,
                    'options' => $options,
                ];
            })
            ->all();

        return [
            'product' => is_array($product)
                ? Arr::only($product, ['category', 'confidence'])
                : [],
            'questions' => $questions,
        ];
    }

    /** @param array<string, mixed> $response */
    private function decodeAssistantJson(array $response): array
    {
        $content = data_get($response, 'choices.0.message.content');

        if (! is_string($content)) {
            throw new RuntimeException('Нейросеть вернула ответ в неизвестном формате.');
        }

        $json = (string) Str::of($content)
            ->trim()
            ->replaceMatches('/^```(?:json)?\s*|\s*```$/u', '');
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Нейросеть вернула некорректный JSON.');
        }

        return $decoded;
    }

    /** @return array<string, mixed> */
    private function decodeStoredQuestionnaire(CardGeneration $generation): array
    {
        $questionnaire = json_decode((string) $generation->prompt_input, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($questionnaire)) {
            throw new RuntimeException('Сохранённый опрос повреждён.');
        }

        return $questionnaire;
    }

    /**
     * @param  array<string, mixed>  $questionnaire
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function validatedQuestionnaireAnswers(array $questionnaire, array $answers): array
    {
        $validatedAnswers = [];

        foreach ($questionnaire['questions'] as $question) {
            $answer = $answers[$question['id']] ?? null;
            $allowedOptions = collect($question['options'])->pluck('label', 'id');
            $selectedIds = Arr::wrap($answer);

            if ($selectedIds === [] || collect($selectedIds)->contains(fn (mixed $id): bool => ! is_string($id) || ! $allowedOptions->has($id))) {
                throw ValidationException::withMessages([
                    "answers.{$question['id']}" => 'Выберите один из предложенных вариантов.',
                ]);
            }

            if ($question['type'] === 'single_choice' && count($selectedIds) !== 1) {
                throw ValidationException::withMessages([
                    "answers.{$question['id']}" => 'Можно выбрать только один вариант.',
                ]);
            }

            $validatedAnswers[$question['id']] = [
                'question' => $question['question'],
                'purpose' => $question['purpose'],
                'selected' => $allowedOptions->only($selectedIds)->values()->all(),
            ];
        }

        return $validatedAnswers;
    }
}
