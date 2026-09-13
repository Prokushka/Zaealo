<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    descriptionWordCount: { type: Number, required: true },
    competitorSearchProcessing: { type: Boolean, default: false },
    hasMarketplaceApiKey: { type: Boolean, default: false },
    marketplace: { type: String, required: true },
    marketplaceName: { type: String, required: true },
});

const emit = defineEmits([
    'select-competitor-cards',
    'api-key-saved',
    'update-description',
    'update-title',
]);

const title = computed({
    get: () => props.form.title,
    set: (value) => emit('update-title', value),
});
const description = computed({
    get: () => props.form.description,
    set: (value) => emit('update-description', value),
});
const apiKeyForm = useForm({ api_key: '', client_id: '' });
const integrationSuccessMessage = ref('');

const saveApiKey = () => {
    apiKeyForm.put(route('integrations.update', props.marketplace), {
        preserveScroll: true,
        onSuccess: () => {
            apiKeyForm.reset();
            integrationSuccessMessage.value = `API-ключ ${props.marketplaceName} сохранён.`;
            emit('api-key-saved', props.marketplace);
        },
    });
};
</script>

<template>
    <div class="flex flex-col gap-5">
        <div>
            <div class="flex items-center justify-between gap-3">
                <label
                    for="card-title"
                    class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >Название товара</label
                >
                <span class="text-xs text-slate-400"
                    >{{ form.title.length }} / 120</span
                >
            </div>
            <input
                id="card-title"
                v-model="title"
                type="text"
                maxlength="120"
                placeholder="Введите продающее название"
                class="mt-2 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-violet-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
        </div>
        <div>
            <div class="flex items-center justify-between gap-3">
                <label
                    for="card-description"
                    class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                    >SEO-описание</label
                >
                <span class="text-xs text-slate-400"
                    >{{ descriptionWordCount }} слов</span
                >
            </div>
            <textarea
                id="card-description"
                v-model="description"
                rows="16"
                maxlength="20000"
                placeholder="Расскажите о преимуществах товара"
                class="mt-2 block w-full resize-none rounded-xl border-slate-300 bg-white text-sm leading-6 text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-violet-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
        </div>
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="integrationSuccessMessage"
                class="flex items-start justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                role="status"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            aria-hidden="true"
                        >
                            <path d="m5 12 4 4L19 6" />
                        </svg>
                    </span>
                    <p class="pt-1 text-sm font-semibold">
                        {{ integrationSuccessMessage }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-1 transition hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 dark:hover:bg-emerald-500/20"
                    aria-label="Закрыть уведомление"
                    @click="integrationSuccessMessage = ''"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="m6 6 12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>
        </Transition>
        <form
            v-if="!hasMarketplaceApiKey"
            class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-500/30 dark:bg-amber-500/10"
            @submit.prevent="saveApiKey"
        >
            <div class="flex items-start gap-3">
                <span
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="M12 8v5M12 17h.01" />
                        <circle cx="12" cy="12" r="9" />
                    </svg>
                </span>
                <div>
                    <p
                        class="text-sm font-bold text-amber-950 dark:text-amber-100"
                    >
                        {{ marketplaceName }} не подключён
                    </p>
                    <p
                        class="mt-1 text-sm leading-5 text-amber-800 dark:text-amber-200"
                    >
                        Без API-ключа нельзя будет автоматически интегрировать
                        карточку в {{ marketplaceName }}. Подключение
                        необязательно: вы можете продолжить без него.
                    </p>
                </div>
            </div>
            <label
                v-if="marketplace === 'ozon'"
                :for="`${marketplace}-client-id`"
                class="mt-4 block text-sm font-semibold text-amber-950 dark:text-amber-100"
            >
                Client ID Ozon
            </label>
            <input
                v-if="marketplace === 'ozon'"
                :id="`${marketplace}-client-id`"
                v-model="apiKeyForm.client_id"
                type="password"
                autocomplete="off"
                placeholder="Введите Client ID"
                class="mt-2 block w-full rounded-xl border-amber-200 bg-white text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-amber-500/30 dark:bg-slate-950 dark:text-white"
            />
            <p
                v-if="apiKeyForm.errors.client_id"
                class="mt-2 text-sm font-medium text-red-600 dark:text-red-400"
            >
                {{ apiKeyForm.errors.client_id }}
            </p>
            <label
                :for="`${marketplace}-api-key`"
                class="mt-4 block text-sm font-semibold text-amber-950 dark:text-amber-100"
            >
                API-ключ {{ marketplaceName }}
            </label>
            <input
                :id="`${marketplace}-api-key`"
                v-model="apiKeyForm.api_key"
                type="password"
                autocomplete="off"
                placeholder="Введите API-ключ"
                class="mt-2 block w-full rounded-xl border-amber-200 bg-white text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-amber-500/30 dark:bg-slate-950 dark:text-white"
            />
            <p
                v-if="apiKeyForm.errors.api_key"
                class="mt-2 text-sm font-medium text-red-600 dark:text-red-400"
            >
                {{ apiKeyForm.errors.api_key }}
            </p>
            <button
                type="submit"
                class="mt-3 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="apiKeyForm.processing"
            >
                Сохранить ключ
            </button>
        </form>
        <div
            v-if="hasMarketplaceApiKey && competitorSearchProcessing"
            class="flex flex-col items-center gap-3 rounded-2xl border border-violet-200 bg-violet-50/70 p-6 text-center dark:border-violet-500/30 dark:bg-violet-500/10"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <span
                class="h-9 w-9 animate-spin rounded-full border-4 border-violet-200 border-t-violet-600 dark:border-slate-700 dark:border-t-violet-400"
            />
            <div>
                <p class="text-sm font-bold text-slate-900 dark:text-white">
                    Ищем похожие карточки
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Получаем данные первых товаров с {{ marketplaceName }}.
                </p>
            </div>
        </div>
    </div>
</template>
