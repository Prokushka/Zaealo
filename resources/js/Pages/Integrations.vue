<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    integrations: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        default: null,
    },
});

const expandedMarketplace = ref(null);
const forms = {
    wildberries: useForm({ api_key: '', client_id: '' }),
    ozon: useForm({ api_key: '', client_id: '' }),
};
const marketplaces = [
    {
        id: 'wildberries',
        title: 'Wildberries',
        description: 'Подключите API-ключ кабинета продавца Wildberries.',
    },
    {
        id: 'ozon',
        title: 'Ozon',
        description: 'Подключите Client ID и API-ключ кабинета продавца Ozon.',
    },
];

const toggleMarketplace = (marketplace) => {
    expandedMarketplace.value =
        expandedMarketplace.value === marketplace ? null : marketplace;
};

const saveApiKey = (marketplace) => {
    const form = forms[marketplace];

    form.put(route('integrations.update', marketplace), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const deleteApiKey = (marketplace) => {
    forms[marketplace].delete(route('integrations.destroy', marketplace), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="API интеграции" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                API интеграции
            </h2>
        </template>

        <section class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
            <div
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"
            >
                <div>
                    <h1
                        class="text-xl font-bold text-slate-950 dark:text-white"
                    >
                        Подключите маркетплейсы
                    </h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Ключи хранятся в зашифрованном виде и доступны только
                        вашему аккаунту.
                    </p>
                </div>

                <p
                    v-if="status"
                    class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                    role="status"
                >
                    {{ status }}
                </p>

                <div class="mt-6 flex flex-col gap-4">
                    <article
                        v-for="marketplace in marketplaces"
                        :key="marketplace.id"
                        class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 p-5 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800"
                            :aria-expanded="
                                expandedMarketplace === marketplace.id
                            "
                            @click="toggleMarketplace(marketplace.id)"
                        >
                            <span>
                                <span
                                    class="block text-base font-bold text-slate-900 dark:text-white"
                                >
                                    {{ marketplace.title }}
                                </span>
                                <span
                                    class="mt-1 block text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ marketplace.description }}
                                </span>
                            </span>
                            <span
                                class="shrink-0 rounded-full px-3 py-1 text-xs font-bold"
                                :class="
                                    integrations[marketplace.id].has_key
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                "
                            >
                                {{
                                    integrations[marketplace.id].has_key
                                        ? 'Ключ добавлен'
                                        : 'Не подключено'
                                }}
                            </span>
                        </button>

                        <form
                            v-if="expandedMarketplace === marketplace.id"
                            class="border-t border-slate-200 p-5 dark:border-slate-700"
                            @submit.prevent="saveApiKey(marketplace.id)"
                        >
                            <template v-if="marketplace.id === 'ozon'">
                                <label
                                    :for="`${marketplace.id}-client-id`"
                                    class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    Client ID
                                </label>
                                <input
                                    :id="`${marketplace.id}-client-id`"
                                    v-model="forms[marketplace.id].client_id"
                                    type="password"
                                    autocomplete="off"
                                    placeholder="Введите Client ID"
                                    class="mt-3 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                                <p
                                    v-if="
                                        forms[marketplace.id].errors.client_id
                                    "
                                    class="mt-2 text-sm font-medium text-red-600 dark:text-red-400"
                                >
                                    {{ forms[marketplace.id].errors.client_id }}
                                </p>
                            </template>
                            <label
                                :for="`${marketplace.id}-api-key`"
                                class="mt-4 block text-sm font-semibold text-slate-800 dark:text-slate-200"
                            >
                                API-ключ{{
                                    marketplace.id === 'ozon' ? ' Ozon' : ''
                                }}
                            </label>
                            <input
                                :id="`${marketplace.id}-api-key`"
                                v-model="forms[marketplace.id].api_key"
                                type="password"
                                autocomplete="off"
                                :placeholder="
                                    integrations[marketplace.id].has_key
                                        ? 'Введите новые ключи для замены'
                                        : 'Введите API-ключ'
                                "
                                class="mt-3 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                            <p
                                v-if="forms[marketplace.id].errors.api_key"
                                class="mt-2 text-sm font-medium text-red-600 dark:text-red-400"
                            >
                                {{ forms[marketplace.id].errors.api_key }}
                            </p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <button
                                    type="submit"
                                    class="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="forms[marketplace.id].processing"
                                >
                                    Сохранить ключ
                                </button>
                                <button
                                    v-if="integrations[marketplace.id].has_key"
                                    type="button"
                                    class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-bold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                    :disabled="forms[marketplace.id].processing"
                                    @click="deleteApiKey(marketplace.id)"
                                >
                                    Удалить ключ
                                </button>
                            </div>
                        </form>
                    </article>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
