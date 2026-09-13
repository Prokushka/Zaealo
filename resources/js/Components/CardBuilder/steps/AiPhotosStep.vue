<script setup>
import Modal from '@/Components/Modal.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: { type: Array, required: true },
    selectedScenarios: { type: Array, required: true },
    expandedCategoryId: { type: String, default: null },
    infographicFeatures: { type: Array, required: true },
    generationSummary: { type: Object, required: true },
    generatedPhotos: { type: Array, required: true },
    processing: { type: Boolean, default: false },
    canGenerate: { type: Boolean, default: false },
    imageCost: { type: Number, required: true },
    selectedCount: { type: Number, default: 0 },
});

const emit = defineEmits([
    'select-category',
    'toggle-scenario',
    'generate',
    'update:infographic-features',
]);
const featuresText = computed({
    get: () => props.infographicFeatures.join(', '),
    set: (value) =>
        emit('update:infographic-features', [
            ...new Set(
                value
                    .split(',')
                    .map((feature) => feature.trim())
                    .filter(Boolean),
            ),
        ]),
});
const isSelected = (optionId) => props.selectedScenarios.includes(optionId);
const categoryHasSelection = (category) =>
    category.options.some((option) => isSelected(option.id));
const selectedPhoto = ref(null);

const photoCategoryLabel = (photo) =>
    props.categories.find((category) => category.id === photo.category)
        ?.title ?? photo.category;

const photoSubcategoryLabel = (photo) => {
    const category = props.categories.find(
        (item) => item.id === photo.category,
    );

    return (
        category?.options.find((option) => option.id === photo.subcategory)
            ?.title ?? photo.subcategory
    );
};

const openPhoto = (photo) => {
    selectedPhoto.value = photo;
};

const closePhoto = () => {
    selectedPhoto.value = null;
};
</script>

<template>
    <div class="flex flex-col gap-5">
        <div>
            <h3
                class="text-sm font-semibold text-slate-800 dark:text-slate-200"
            >
                Выберите категорию фото
            </h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Определите, какой кадр нужно создать для карточки товара.
            </p>
        </div>
        <div class="flex flex-col gap-3">
            <div v-for="category in categories" :key="category.id">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-4 rounded-xl border p-4 text-left transition"
                    :class="
                        expandedCategoryId === category.id ||
                        categoryHasSelection(category)
                            ? 'border-violet-300 bg-violet-50 dark:border-violet-500/70 dark:bg-violet-500/10'
                            : 'border-slate-200 bg-white hover:border-violet-400 dark:border-slate-700 dark:bg-slate-950'
                    "
                    @click="emit('select-category', category.id)"
                >
                    <span
                        ><span
                            class="block text-sm font-bold text-slate-800 dark:text-slate-200"
                            >{{ category.title }}</span
                        ><span
                            class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >{{ category.description }}</span
                        ></span
                    ><span class="text-violet-600">{{
                        categoryHasSelection(category) ? '✓' : '⌄'
                    }}</span>
                </button>
                <div
                    v-if="expandedCategoryId === category.id"
                    class="mt-2 flex flex-col gap-2 rounded-xl border border-violet-200 bg-violet-50/50 p-3 dark:border-violet-500/30 dark:bg-violet-500/5"
                >
                    <button
                        v-for="option in category.options"
                        :key="option.id"
                        type="button"
                        class="rounded-xl border px-4 py-3 text-left transition"
                        :class="
                            isSelected(option.id)
                                ? 'border-violet-600 bg-violet-600 text-white'
                                : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
                        "
                        @click="emit('toggle-scenario', option.id)"
                    >
                        <span class="block text-sm font-bold">{{
                            option.title
                        }}</span
                        ><span class="mt-1 block text-xs leading-5">{{
                            option.description
                        }}</span>
                    </button>
                </div>
            </div>
        </div>
        <div
            class="rounded-2xl border border-violet-200 bg-violet-50/60 p-4 dark:border-violet-500/30 dark:bg-violet-500/5"
        >
            <label
                for="infographic-features"
                class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                >Ключевые качества для характеристик и инфографики</label
            ><input
                id="infographic-features"
                v-model="featuresText"
                type="text"
                class="mt-3 block w-full rounded-xl border-violet-200 bg-white text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-violet-500/30 dark:bg-slate-950 dark:text-white"
            />
        </div>
        <div
            v-if="generationSummary.total > 0"
            class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950"
        >
            <p class="text-sm font-bold text-slate-800 dark:text-slate-200">
                ИИ-фото: {{ generationSummary.completed }} /
                {{ generationSummary.total }}
            </p>
            <div
                class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
            >
                <div
                    class="h-full rounded-full bg-violet-600"
                    :style="{
                        width: `${Math.round(((generationSummary.completed + generationSummary.failed) / generationSummary.total) * 100)}%`,
                    }"
                />
            </div>
            <div
                v-if="generatedPhotos.length"
                class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
                <button
                    v-for="photo in generatedPhotos"
                    :key="photo.id"
                    type="button"
                    class="overflow-hidden rounded-xl border border-slate-200 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:focus-visible:ring-offset-slate-950"
                    :class="
                        photo.status === 'completed'
                            ? 'cursor-zoom-in hover:border-violet-400 hover:shadow-md'
                            : 'cursor-default'
                    "
                    :disabled="photo.status !== 'completed'"
                    :aria-label="
                        photo.status === 'completed'
                            ? 'Открыть сгенерированное фото товара'
                            : undefined
                    "
                    @click="openPhoto(photo)"
                >
                    <img
                        v-if="photo.status === 'completed'"
                        :src="photo.url"
                        alt="Сгенерированное фото товара"
                        class="aspect-square w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex aspect-square items-center justify-center p-3 text-xs text-slate-500"
                    >
                        {{
                            photo.status === 'failed'
                                ? 'Ошибка генерации'
                                : 'Создаётся'
                        }}
                    </div>
                    <div
                        class="flex flex-col gap-0.5 border-t border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"
                    >
                        <span
                            class="text-[11px] font-bold text-violet-700 dark:text-violet-300"
                            >{{ photoCategoryLabel(photo) }}</span
                        >
                        <span
                            class="truncate text-[11px] text-slate-500 dark:text-slate-400"
                            >{{ photoSubcategoryLabel(photo) }}</span
                        >
                    </div>
                </button>
            </div>
        </div>
        <button
            type="button"
            class="flex w-full items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-45"
            :disabled="processing || !canGenerate"
            @click="emit('generate')"
        >
            {{
                processing
                    ? 'Ставим в очередь…'
                    : selectedCount > 0
                      ? `Сгенерировать ${selectedCount} фото · ${selectedCount * imageCost} ZARQ`
                      : `Выберите фото · ${imageCost} ZARQ за одно`
            }}
        </button>
        <p class="-mt-2 text-center text-xs text-slate-500 dark:text-slate-400">
            Оплата списывается при постановке в очередь. При окончательной
            ошибке генерации ZARQ вернутся автоматически.
        </p>

        <Modal
            :show="selectedPhoto !== null"
            max-width="2xl"
            @close="closePhoto"
        >
            <div class="relative bg-slate-950 p-3 sm:p-4">
                <button
                    type="button"
                    class="absolute right-5 top-5 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-950/80 text-white shadow-lg transition hover:bg-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                    aria-label="Закрыть просмотр фото"
                    @click="closePhoto"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="m6 6 12 12M18 6 6 18" />
                    </svg>
                </button>
                <img
                    v-if="selectedPhoto"
                    :src="selectedPhoto.url"
                    alt="Сгенерированное фото товара в полном размере"
                    class="mx-auto max-h-[85vh] w-auto max-w-full object-contain"
                />
            </div>
        </Modal>
    </div>
</template>
