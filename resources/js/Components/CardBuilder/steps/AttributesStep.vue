<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    marketplace: { type: String, default: null },
    categoryMatch: { type: Object, default: null },
    generationId: { type: [Number, String], default: null },
    initialAttributes: { type: Object, default: () => ({}) },
    modelValue: { type: Object, default: null },
});

const emit = defineEmits(['update:attributes', 'update:modelValue']);
const isSearchOpen = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const searchError = ref('');
const isSearching = ref(false);
const donorUrl = ref('');
const attributes = ref({});
const fillError = ref('');
const isFilling = ref(false);
const saveError = ref('');
const saveMessage = ref('');
const isSaving = ref(false);
let searchTimer = null;
let searchController = null;

const categoryIdFrom = (category) => {
    if (!category || typeof category !== 'object') {
        return null;
    }

    const rawId =
        props.marketplace === 'ozon'
            ? (category.description_category_id ??
              category.category_id ??
              category.id)
            : (category.subject_id ?? category.category_id ?? category.id);
    const categoryId = Number(rawId);

    return Number.isInteger(categoryId) && categoryId > 0 ? categoryId : null;
};

const normalizeCategory = (category) => {
    const categoryId = categoryIdFrom(category);
    const fullPath =
        typeof category?.full_path === 'string'
            ? category.full_path.trim()
            : '';
    const rawTypeId = category?.type_id;
    const typeId = Number(rawTypeId);

    if (!categoryId || !fullPath) {
        return null;
    }

    return {
        category_id: categoryId,
        type_id:
            props.marketplace === 'ozon' &&
            Number.isInteger(typeId) &&
            typeId > 0
                ? typeId
                : null,
        full_path: fullPath,
    };
};

const candidates = computed(() =>
    Array.isArray(props.categoryMatch?.candidates)
        ? props.categoryMatch.candidates.map(normalizeCategory).filter(Boolean)
        : [],
);
const selectedCategory = computed(() => normalizeCategory(props.modelValue));
const selectedIdentity = computed(() => {
    const category = selectedCategory.value;

    return category
        ? `${category.category_id}:${category.type_id ?? ''}`
        : null;
});
const alternatives = computed(() =>
    candidates.value.filter(
        (category) =>
            `${category.category_id}:${category.type_id ?? ''}` !==
            selectedIdentity.value,
    ),
);
const canFill = computed(
    () => selectedCategory.value !== null && props.generationId !== null,
);
const isCategoryLocked = computed(
    () => isFilling.value || Object.keys(attributes.value).length > 0,
);

const editableValue = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
};

const hasEditableValue = (value) => {
    if (value === null || value === undefined) {
        return false;
    }

    if (typeof value === 'string') {
        return value.trim().length > 0;
    }

    if (Array.isArray(value)) {
        return value.some(hasEditableValue);
    }

    if (typeof value === 'object') {
        return Object.keys(value).length > 0;
    }

    return true;
};

const editableAttributes = (rawAttributes) =>
    Object.fromEntries(
        Object.entries(rawAttributes)
            .filter(([, value]) => hasEditableValue(value))
            .map(([key, value]) => [key, editableValue(value)]),
    );

const responseJson = async (response) => {
    const body = await response.text();
    let result;

    try {
        result = JSON.parse(body);
    } catch {
        throw new Error(
            'Сервер вернул некорректный ответ. Обновите страницу и повторите попытку.',
        );
    }

    if (!response.ok) {
        const validationMessage = Object.values(result.errors ?? {})
            .flat()
            .find((message) => typeof message === 'string');

        throw new Error(
            result.message ??
                validationMessage ??
                'Не удалось выполнить запрос. Повторите попытку.',
        );
    }

    return result;
};

const selectCategory = (category) => {
    if (isCategoryLocked.value) {
        return;
    }

    const normalized = normalizeCategory(category);

    if (!normalized) {
        return;
    }

    emit('update:modelValue', normalized);
    isSearchOpen.value = false;
    searchError.value = '';
};

const fillAttributes = async () => {
    if (!canFill.value || isFilling.value) {
        return;
    }

    isFilling.value = true;
    isSearchOpen.value = false;
    fillError.value = '';
    saveError.value = '';
    saveMessage.value = '';

    try {
        const response = await fetch(
            route('card-generations.attributes.store', props.generationId),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content'),
                },
                body: JSON.stringify({
                    category_id: selectedCategory.value.category_id,
                    type_id: selectedCategory.value.type_id,
                    donor_url: donorUrl.value.trim() || null,
                }),
            },
        );
        const result = await responseJson(response);

        attributes.value =
            result.attributes && typeof result.attributes === 'object'
                ? editableAttributes(result.attributes)
                : {};
    } catch (error) {
        fillError.value =
            error instanceof Error
                ? error.message
                : 'Не удалось заполнить характеристики. Повторите попытку.';
    } finally {
        isFilling.value = false;
    }
};

const saveAttributes = async () => {
    if (!props.generationId || isSaving.value) {
        return;
    }

    isSaving.value = true;
    saveError.value = '';
    saveMessage.value = '';

    try {
        const response = await fetch(
            route('card-generations.attributes.update', props.generationId),
            {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content'),
                },
                body: JSON.stringify({ attributes: attributes.value }),
            },
        );
        const result = await responseJson(response);

        attributes.value = editableAttributes(result.attributes ?? {});
        saveMessage.value = 'Изменения сохранены.';
    } catch (error) {
        saveError.value =
            error instanceof Error
                ? error.message
                : 'Не удалось сохранить характеристики.';
    } finally {
        isSaving.value = false;
    }
};

const markAttributesChanged = () => {
    saveError.value = '';
    saveMessage.value = '';
};

watch(
    () => props.initialAttributes,
    (initialAttributes) => {
        attributes.value = editableAttributes(initialAttributes ?? {});
    },
    { deep: true, immediate: true },
);

watch(
    attributes,
    (updatedAttributes) => {
        emit('update:attributes', { ...updatedAttributes });
    },
    { deep: true },
);

watch(
    () => props.categoryMatch,
    (categoryMatch) => {
        if (selectedCategory.value !== null) {
            return;
        }

        const recommendedId = Number(categoryMatch?.recommended_id);

        if (!Number.isInteger(recommendedId) || recommendedId <= 0) {
            return;
        }

        const recommendation = candidates.value.find(
            (category) => category.category_id === recommendedId,
        );

        if (recommendation) {
            selectCategory(recommendation);
        }
    },
    { immediate: true },
);

watch(searchQuery, (query) => {
    if (searchTimer !== null) {
        window.clearTimeout(searchTimer);
    }

    if (searchController !== null) {
        searchController.abort();
        searchController = null;
    }

    const normalizedQuery = query.trim();
    searchResults.value = [];
    searchError.value = '';

    if (normalizedQuery.length < 2 || !props.marketplace) {
        isSearching.value = false;

        return;
    }

    searchTimer = window.setTimeout(async () => {
        searchController = new AbortController();
        isSearching.value = true;

        try {
            const url = new URL(route('marketplace-categories.search'));
            url.searchParams.set('marketplace', props.marketplace);
            url.searchParams.set('query', normalizedQuery);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: searchController.signal,
            });

            if (!response.ok) {
                throw new Error(
                    'Не удалось найти категории. Повторите попытку.',
                );
            }

            const result = await response.json();
            searchResults.value = Array.isArray(result.categories)
                ? result.categories.map(normalizeCategory).filter(Boolean)
                : [];
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            searchError.value =
                error instanceof Error
                    ? error.message
                    : 'Не удалось найти категории. Повторите попытку.';
        } finally {
            isSearching.value = false;
        }
    }, 250);
});

onBeforeUnmount(() => {
    if (searchTimer !== null) {
        window.clearTimeout(searchTimer);
    }

    searchController?.abort();
});
</script>

<template>
    <div class="flex flex-col gap-5">
        <div>
            <h3
                class="text-sm font-semibold text-slate-800 dark:text-slate-200"
            >
                Категория товара
            </h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                ИИ предложил категорию по названию товара. При необходимости
                выберите более точную.
            </p>
        </div>

        <input
            type="hidden"
            name="category_id"
            :value="selectedCategory?.category_id ?? ''"
        />
        <input
            v-if="marketplace === 'ozon'"
            type="hidden"
            name="type_id"
            :value="selectedCategory?.type_id ?? ''"
        />

        <div
            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950"
        >
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                Выбрано
            </p>
            <p
                v-if="selectedCategory"
                class="mt-1 text-sm font-semibold text-slate-900 dark:text-white"
            >
                {{ selectedCategory.full_path }}
            </p>
            <p v-else class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Категория пока не выбрана.
            </p>
        </div>

        <div
            v-if="alternatives.length && !isCategoryLocked"
            class="flex flex-col gap-2"
        >
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                Альтернативы
            </p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="category in alternatives"
                    :key="`${category.category_id}:${category.type_id ?? ''}`"
                    type="button"
                    class="rounded-full border border-violet-200 bg-violet-50 px-3 py-1.5 text-left text-xs font-semibold text-violet-700 transition hover:border-violet-400 hover:bg-violet-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300"
                    @click="selectCategory(category)"
                >
                    {{ category.full_path }}
                </button>
            </div>
        </div>

        <div v-if="!isCategoryLocked" class="flex flex-col gap-3">
            <button
                type="button"
                class="w-fit text-sm font-semibold text-violet-700 transition hover:text-violet-900 focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-violet-500 dark:text-violet-300 dark:hover:text-violet-200"
                @click="isSearchOpen = !isSearchOpen"
            >
                {{ isSearchOpen ? 'Скрыть поиск' : 'Выбрать другую категорию' }}
            </button>
            <div
                v-if="isSearchOpen"
                class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-950"
            >
                <input
                    id="marketplace-category-search"
                    v-model="searchQuery"
                    type="search"
                    autocomplete="off"
                    placeholder="Начните вводить категорию"
                    class="block w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-violet-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                />
                <p
                    v-if="isSearching"
                    class="mt-3 text-sm text-slate-500 dark:text-slate-400"
                >
                    Ищем категории…
                </p>
                <p
                    v-else-if="searchError"
                    class="mt-3 text-sm text-red-600 dark:text-red-400"
                >
                    {{ searchError }}
                </p>
                <div
                    v-else-if="searchResults.length"
                    class="mt-3 flex max-h-60 flex-col overflow-y-auto rounded-lg border border-slate-200 p-1 dark:border-slate-800"
                >
                    <button
                        v-for="category in searchResults"
                        :key="`search-${category.category_id}:${category.type_id ?? ''}`"
                        type="button"
                        class="rounded-md px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-violet-50 hover:text-violet-800 focus:outline-none focus-visible:bg-violet-50 dark:text-slate-200 dark:hover:bg-violet-500/10 dark:hover:text-violet-200"
                        @click="selectCategory(category)"
                    >
                        {{ category.full_path }}
                    </button>
                </div>
                <p
                    v-else-if="searchQuery.trim().length >= 2"
                    class="mt-3 text-sm text-slate-500 dark:text-slate-400"
                >
                    Категории не найдены.
                </p>
            </div>
        </div>

        <div
            class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950"
        >
            <template v-if="!Object.keys(attributes).length">
                <div>
                    <h4
                        class="text-sm font-semibold text-slate-900 dark:text-white"
                    >
                        Заполнить характеристики
                    </h4>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Вставьте ссылку или артикул карточки-донора либо
                        оставьте поле пустым для анализа ИИ.
                    </p>
                </div>
                <label
                    class="flex flex-col gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                >
                    Ссылка или артикул донора
                    <input
                        v-model="donorUrl"
                        type="text"
                        maxlength="2048"
                        placeholder="Например, https://www.ozon.ru/product/... или 123456"
                        class="rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-violet-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                </label>
                <button
                    type="button"
                    :disabled="!canFill || isFilling"
                    class="w-fit rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:bg-violet-300 dark:disabled:bg-slate-700"
                    @click="fillAttributes"
                >
                    {{
                        isFilling
                            ? 'Заполняем…'
                            : donorUrl.trim()
                              ? 'Скопировать характеристики'
                              : 'Заполнить через ИИ'
                    }}
                </button>
                <p
                    v-if="!canFill"
                    class="text-xs text-amber-700 dark:text-amber-300"
                >
                    Выберите категорию, чтобы заполнить характеристики.
                </p>
                <p
                    v-if="fillError"
                    class="text-sm text-red-600 dark:text-red-400"
                >
                    {{ fillError }}
                </p>
            </template>
            <div
                v-if="Object.keys(attributes).length"
                class="flex flex-col gap-3 border-t border-slate-200 pt-3 dark:border-slate-800"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        v-for="(value, key, index) in attributes"
                        :key="key"
                        :for="`product-attribute-${index}`"
                        class="flex flex-col gap-1.5 rounded-lg bg-slate-50 p-3 text-sm font-medium text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        {{ key }}
                        <input
                            :id="`product-attribute-${index}`"
                            v-model="attributes[key]"
                            type="text"
                            maxlength="5000"
                            class="rounded-lg border-slate-300 bg-white text-sm font-normal text-slate-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            @input="markAttributesChanged"
                            @blur="saveAttributes"
                        />
                    </label>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <p
                        v-if="isSaving"
                        class="text-sm text-slate-500 dark:text-slate-400"
                    >
                        Сохраняем…
                    </p>
                    <p
                        v-if="saveMessage"
                        class="text-sm text-emerald-600 dark:text-emerald-400"
                    >
                        {{ saveMessage }}
                    </p>
                    <p
                        v-if="saveError"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
                        {{ saveError }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
