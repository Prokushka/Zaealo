<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    generations: { type: Object, required: true },
});

const archiveProcessingId = ref(null);
const archiveErrors = ref({});

const marketplaceName = (marketplace) =>
    marketplace === 'ozon' ? 'Ozon' : 'Wildberries';

const statusName = (status) =>
    ({
        completed: 'Готова',
        processing: 'Создаётся',
        pending: 'Ожидает',
        awaiting_answers: 'Нужны ответы',
        failed: 'Ошибка',
    })[status] ?? status;

const formattedDate = (date) =>
    new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));

const paginationLabel = (label) => {
    if (label.includes('Previous')) {
        return 'Назад';
    }

    if (label.includes('Next')) {
        return 'Далее';
    }

    return label;
};

const responseJson = async (response) => {
    const body = await response.text();
    let result;

    try {
        result = JSON.parse(body);
    } catch {
        throw new Error('Сервер вернул некорректный ответ.');
    }

    if (!response.ok) {
        const validationMessage = Object.values(result.errors ?? {})
            .flat()
            .find((message) => typeof message === 'string');

        throw new Error(
            validationMessage ??
                result.message ??
                'Не удалось подготовить архив.',
        );
    }

    return result;
};

const downloadArchive = async (generation) => {
    if (archiveProcessingId.value !== null) {
        return;
    }

    archiveProcessingId.value = generation.id;
    archiveErrors.value = { ...archiveErrors.value, [generation.id]: '' };

    try {
        const response = await fetch(
            route('card-generations.exports.archive', generation.id),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content'),
                },
                body: JSON.stringify({}),
            },
        );
        const result = await responseJson(response);
        const download = document.createElement('a');

        download.href = result.download_url;
        document.body.appendChild(download);
        download.click();
        download.remove();

        router.reload({
            only: ['auth', 'generations'],
            preserveScroll: true,
            preserveState: true,
        });
    } catch (error) {
        archiveErrors.value = {
            ...archiveErrors.value,
            [generation.id]:
                error instanceof Error
                    ? error.message
                    : 'Не удалось подготовить архив.',
        };
    } finally {
        archiveProcessingId.value = null;
    }
};
</script>

<template>
    <Head title="Мои карточки" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1
                        class="text-lg font-bold text-slate-900 dark:text-white"
                    >
                        Мои карточки
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Открывайте сохранённые версии, редактируйте и скачивайте
                        результаты.
                    </p>
                </div>
                <Link
                    :href="route('dashboard')"
                    class="shrink-0 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-violet-700"
                >
                    Новая карточка
                </Link>
            </div>
        </template>

        <section
            class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8"
        >
            <div
                v-if="generations.data.length"
                class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
            >
                <article
                    v-for="generation in generations.data"
                    :key="generation.id"
                    class="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="relative aspect-[4/3] bg-slate-100 dark:bg-slate-800"
                    >
                        <img
                            v-if="generation.preview_url"
                            :src="generation.preview_url"
                            :alt="generation.title || 'Фото товара'"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-full items-center justify-center text-sm font-semibold text-slate-400"
                        >
                            Нет превью
                        </div>
                        <span
                            class="absolute left-3 top-3 rounded-full px-3 py-1 text-xs font-bold text-white shadow-sm"
                            :class="
                                generation.marketplace === 'ozon'
                                    ? 'bg-blue-600'
                                    : 'bg-fuchsia-700'
                            "
                        >
                            {{ marketplaceName(generation.marketplace) }}
                        </span>
                        <span
                            class="absolute right-3 top-3 rounded-full bg-white/90 px-3 py-1 text-xs font-bold text-slate-700 shadow-sm backdrop-blur dark:bg-slate-950/85 dark:text-slate-200"
                        >
                            {{ statusName(generation.status) }}
                        </span>
                    </div>

                    <div class="flex flex-1 flex-col gap-4 p-5">
                        <div class="flex flex-col gap-2">
                            <h2
                                class="line-clamp-2 text-base font-bold leading-6 text-slate-950 dark:text-white"
                            >
                                {{
                                    generation.title || 'Карточка без названия'
                                }}
                            </h2>
                            <p
                                class="line-clamp-2 min-h-10 text-sm leading-5 text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    generation.category ||
                                    'Категория не выбрана'
                                }}
                            </p>
                            <time
                                :datetime="generation.created_at"
                                class="text-xs font-medium text-slate-400"
                            >
                                {{ formattedDate(generation.created_at) }}
                            </time>
                        </div>

                        <div class="mt-auto flex flex-col gap-2">
                            <Link
                                v-if="generation.can_open"
                                :href="
                                    route('dashboard', {
                                        card_id: generation.id,
                                    })
                                "
                                class="flex w-full items-center justify-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-violet-700"
                            >
                                Открыть / Редактировать
                            </Link>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="w-full cursor-not-allowed rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-400 dark:bg-slate-800"
                            >
                                Карточка не завершена
                            </button>

                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                    :disabled="
                                        !generation.can_open ||
                                        archiveProcessingId !== null
                                    "
                                    @click="downloadArchive(generation)"
                                >
                                    {{
                                        archiveProcessingId === generation.id
                                            ? 'Готовим…'
                                            : generation.archive_cost > 0
                                              ? `ZIP · ${generation.archive_cost} ZARQ`
                                              : 'Скачать ZIP'
                                    }}
                                </button>
                                <a
                                    :href="
                                        route(
                                            'card-generations.exports.json',
                                            generation.id,
                                        )
                                    "
                                    class="flex items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                    :class="
                                        generation.can_open
                                            ? ''
                                            : 'pointer-events-none opacity-50'
                                    "
                                >
                                    Скачать JSON
                                </a>
                            </div>
                            <p
                                v-if="archiveErrors[generation.id]"
                                class="text-xs font-semibold text-red-600 dark:text-red-400"
                                role="alert"
                            >
                                {{ archiveErrors[generation.id] }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">
                    Здесь появятся ваши карточки
                </h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Создайте первую карточку товара, чтобы вернуться к ней
                    позже.
                </p>
                <Link
                    :href="route('dashboard')"
                    class="mt-5 inline-flex rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-violet-700"
                >
                    Создать карточку
                </Link>
            </div>

            <nav
                v-if="generations.links.length > 3"
                class="mt-8 flex flex-wrap justify-center gap-2"
                aria-label="Пагинация"
            >
                <Link
                    v-for="link in generations.links"
                    :key="link.label"
                    :href="link.url || ''"
                    preserve-scroll
                    class="min-w-10 rounded-lg border px-3 py-2 text-center text-sm font-semibold transition"
                    :class="[
                        link.active
                            ? 'border-violet-600 bg-violet-600 text-white'
                            : 'border-slate-300 bg-white text-slate-700 hover:border-violet-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
                        link.url ? '' : 'pointer-events-none opacity-40',
                    ]"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
            </nav>
        </section>
    </AuthenticatedLayout>
</template>
