<script setup>
import { computed } from 'vue';

const props = defineProps({
    marketplaceName: { type: String, required: true },
    exportCost: { type: Number, required: true },
    imageCost: { type: Number, required: true },
    hasGeneratedPhotos: { type: Boolean, default: false },
    hasExistingArchive: { type: Boolean, default: false },
    hasMarketplaceApiKey: { type: Boolean, default: false },
    hasPendingAiPhotos: { type: Boolean, default: false },
    categorySelected: { type: Boolean, default: false },
    archiveProcessing: { type: Boolean, default: false },
    publicationProcessing: { type: Boolean, default: false },
    exportState: { type: Object, default: null },
    error: { type: String, default: '' },
});

const emit = defineEmits(['download-archive', 'publish']);

const statusMessage = computed(() => {
    const statuses = {
        queued: 'Отправка карточки поставлена в очередь.',
        submitting: `Передаём черновик в ${props.marketplaceName}…`,
        waiting: `${props.marketplaceName} обрабатывает черновик…`,
        completed: `Черновик успешно создан в ${props.marketplaceName}.`,
        failed:
            props.exportState?.error ??
            `${props.marketplaceName} отклонил черновик.`,
    };

    return statuses[props.exportState?.status] ?? '';
});
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="rounded-xl bg-slate-50 p-5 dark:bg-slate-950">
            <h3 class="font-bold text-slate-900 dark:text-white">Экспорт</h3>
            <p
                class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
            >
                ZIP со всеми исходными и сгенерированными фото скачивается
                отдельно. В {{ marketplaceName }} отдельной кнопкой отправятся
                фото и все сохранённые поля из шага «Категория».
            </p>
            <dl class="mt-4 flex flex-col gap-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">
                        Скачать ZIP
                    </dt>
                    <dd class="font-bold text-violet-700 dark:text-violet-300">
                        {{
                            exportCost === 0
                                ? 'Бесплатно'
                                : `${exportCost} ZARQ`
                        }}
                    </dd>
                </div>
                <div
                    class="flex justify-between gap-4 border-t border-slate-200 pt-3 dark:border-slate-800"
                >
                    <dt class="text-slate-500 dark:text-slate-400">
                        Создание черновика
                    </dt>
                    <dd
                        class="font-semibold text-slate-800 dark:text-slate-200"
                    >
                        Без списания
                    </dd>
                </div>
            </dl>
            <p
                class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400"
            >
                <template v-if="hasGeneratedPhotos">
                    ZIP бесплатный, потому что есть готовое ИИ-фото.
                </template>
                <template v-else-if="hasExistingArchive">
                    ZIP уже оплачен, повторное скачивание бесплатно.
                </template>
                <template v-else>
                    Без готового ИИ-фото ZIP стоит {{ exportCost }} ZARQ. Каждое
                    ИИ-фото стоит {{ imageCost }} ZARQ.
                </template>
            </p>
        </div>

        <p
            v-if="error"
            class="whitespace-pre-line rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-300"
            role="alert"
        >
            {{ error }}
        </p>

        <p
            v-if="statusMessage"
            class="whitespace-pre-line rounded-xl px-4 py-3 text-sm font-semibold"
            :class="
                exportState?.status === 'failed'
                    ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'
                    : exportState?.status === 'completed'
                      ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                      : 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300'
            "
            aria-live="polite"
        >
            {{ statusMessage }}
        </p>

        <div class="grid gap-3 sm:grid-cols-2">
            <button
                type="button"
                class="flex items-center justify-center rounded-xl border border-violet-300 bg-white px-5 py-3 text-sm font-bold text-violet-700 transition hover:bg-violet-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-45 dark:border-violet-500/50 dark:bg-slate-950 dark:text-violet-300 dark:hover:bg-violet-500/10"
                :disabled="archiveProcessing || hasPendingAiPhotos"
                @click="emit('download-archive')"
            >
                {{
                    archiveProcessing
                        ? 'Готовим ZIP…'
                        : exportCost === 0
                          ? 'Скачать ZIP со всеми фото'
                          : `Скачать ZIP · ${exportCost} ZARQ`
                }}
            </button>

            <button
                type="button"
                class="flex items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-violet-600/20 transition hover:bg-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-45"
                :disabled="
                    publicationProcessing ||
                    !hasMarketplaceApiKey ||
                    !categorySelected ||
                    hasPendingAiPhotos
                "
                @click="emit('publish')"
            >
                {{
                    publicationProcessing
                        ? 'Отправляем…'
                        : `Загрузить черновик в ${marketplaceName}`
                }}
            </button>
        </div>

        <p
            v-if="hasPendingAiPhotos"
            class="text-center text-xs text-amber-700 dark:text-amber-300"
        >
            Дождитесь окончания генерации фото.
        </p>
        <p
            v-else-if="!hasMarketplaceApiKey"
            class="text-center text-xs text-slate-500 dark:text-slate-400"
        >
            Для загрузки черновика подключите API-ключ {{ marketplaceName }} в
            интеграциях. ZIP можно скачать без ключа.
        </p>
        <p
            v-else-if="!categorySelected"
            class="text-center text-xs text-amber-700 dark:text-amber-300"
        >
            Для загрузки черновика выберите категорию товара. ZIP можно скачать
            без категории.
        </p>
    </div>
</template>
