<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tickets: { type: Object, required: true },
    categories: { type: Array, required: true },
    status: { type: String, default: null },
});

const page = usePage();
const copied = ref(false);
const fileInput = ref(null);
const isComposerOpen = ref(props.tickets.data.length === 0);
const supportCode = computed(() => page.props.auth?.user?.support_code ?? '');
const form = useForm({
    category: props.categories[0]?.value ?? 'other',
    subject: '',
    body: '',
    attachments: [],
});

const statusClasses = {
    waiting_for_support:
        'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300',
    waiting_for_user:
        'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300',
    resolved:
        'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300',
};

const copySupportCode = async () => {
    await navigator.clipboard.writeText(supportCode.value);
    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const selectFiles = (event) => {
    form.attachments = Array.from(event.target.files ?? []);
};

const submit = () => {
    form.post(route('support.tickets.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.category = props.categories[0]?.value ?? 'other';
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};

const formatDate = (value) =>
    new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));

const paginationLabel = (label) => {
    if (label.includes('Previous')) {
        return 'Назад';
    }

    if (label.includes('Next')) {
        return 'Вперёд';
    }

    return label;
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Поддержка" />

        <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p
                        class="text-sm font-bold uppercase tracking-wider text-violet-600 dark:text-violet-400"
                    >
                        Поддержка ZARQ
                    </p>
                    <h1
                        class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-white"
                    >
                        Ваши обращения
                    </h1>
                    <p
                        class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-400"
                    >
                        Опишите вопрос и следите за ответами поддержки в одной
                        переписке.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-violet-700"
                    @click="isComposerOpen = !isComposerOpen"
                >
                    {{ isComposerOpen ? 'Скрыть форму' : 'Новое обращение' }}
                </button>
            </div>

            <p
                v-if="status"
                class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                role="status"
            >
                {{ status }}
            </p>

            <section
                v-if="isComposerOpen"
                class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-7"
            >
                <h2 class="text-lg font-bold text-slate-950 dark:text-white">
                    Новое обращение
                </h2>
                <form class="mt-5 space-y-5" @submit.prevent="submit">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label
                                for="support-category"
                                class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                            >
                                Категория
                            </label>
                            <select
                                id="support-category"
                                v-model="form.category"
                                class="mt-2 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            >
                                <option
                                    v-for="category in categories"
                                    :key="category.value"
                                    :value="category.value"
                                >
                                    {{ category.label }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.category"
                                class="mt-2 text-sm font-medium text-red-600 dark:text-red-400"
                            >
                                {{ form.errors.category }}
                            </p>
                        </div>
                        <div>
                            <label
                                for="support-subject"
                                class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                            >
                                Тема
                            </label>
                            <input
                                id="support-subject"
                                v-model="form.subject"
                                type="text"
                                maxlength="150"
                                placeholder="Кратко опишите проблему"
                                class="mt-2 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                            <p
                                v-if="form.errors.subject"
                                class="mt-2 text-sm font-medium text-red-600 dark:text-red-400"
                            >
                                {{ form.errors.subject }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label
                            for="support-body"
                            class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                            >Сообщение</label
                        >
                        <textarea
                            id="support-body"
                            v-model="form.body"
                            rows="6"
                            maxlength="5000"
                            placeholder="Расскажите, что произошло и какого результата вы ожидали"
                            class="mt-2 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                        <p
                            v-if="form.errors.body"
                            class="mt-2 text-sm font-medium text-red-600 dark:text-red-400"
                        >
                            {{ form.errors.body }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="support-attachments"
                            class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                            >Вложения</label
                        >
                        <input
                            id="support-attachments"
                            ref="fileInput"
                            type="file"
                            multiple
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-400 dark:file:bg-slate-800 dark:file:text-slate-200"
                            @change="selectFiles"
                        />
                        <p
                            class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                        >
                            До 5 изображений или PDF, каждый файл до 10 МБ.
                        </p>
                        <p
                            v-if="
                                form.errors.attachments ||
                                form.errors['attachments.0']
                            "
                            class="mt-2 text-sm font-medium text-red-600 dark:text-red-400"
                        >
                            {{
                                form.errors.attachments ||
                                form.errors['attachments.0']
                            }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? 'Отправляем…'
                                : 'Создать обращение'
                        }}
                    </button>
                </form>
            </section>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <section
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                    >
                        <h2 class="font-bold text-slate-950 dark:text-white">
                            История обращений
                        </h2>
                    </div>

                    <div
                        v-if="tickets.data.length"
                        class="divide-y divide-slate-200 dark:divide-slate-800"
                    >
                        <Link
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            :href="route('support.tickets.show', ticket.id)"
                            class="block p-5 transition hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-400"
                                            >#{{ ticket.id }}</span
                                        >
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                                            :class="
                                                statusClasses[
                                                    ticket.status.value
                                                ]
                                            "
                                        >
                                            {{ ticket.status.label }}
                                        </span>
                                    </div>
                                    <h3
                                        class="mt-2 truncate font-bold text-slate-950 dark:text-white"
                                    >
                                        {{ ticket.subject }}
                                    </h3>
                                    <p
                                        class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ ticket.category.label }}
                                    </p>
                                </div>
                                <time
                                    class="shrink-0 text-xs font-medium text-slate-400"
                                    :datetime="ticket.last_message_at"
                                >
                                    {{ formatDate(ticket.last_message_at) }}
                                </time>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="px-5 py-14 text-center">
                        <p
                            class="font-semibold text-slate-700 dark:text-slate-300"
                        >
                            Обращений пока нет
                        </p>
                        <p
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            Создайте первое обращение, если вам нужна помощь.
                        </p>
                    </div>

                    <nav
                        v-if="tickets.links?.length > 3"
                        class="flex flex-wrap gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800"
                        aria-label="Страницы"
                    >
                        <template
                            v-for="link in tickets.links"
                            :key="link.label"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="rounded-lg px-3 py-2 text-sm font-semibold"
                                :class="
                                    link.active
                                        ? 'bg-violet-600 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                                "
                            >
                                {{ paginationLabel(link.label) }}
                            </Link>
                        </template>
                    </nav>
                </section>

                <aside
                    class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="text-xs font-bold uppercase tracking-wider text-slate-400"
                    >
                        Код клиента
                    </p>
                    <code
                        class="mt-3 block break-all text-base font-bold tracking-wide text-slate-950 dark:text-white"
                        >{{ supportCode }}</code
                    >
                    <button
                        type="button"
                        class="mt-4 w-full rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        @click="copySupportCode"
                    >
                        {{ copied ? 'Скопировано' : 'Скопировать код' }}
                    </button>
                    <p
                        class="mt-4 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        Код помогает сотруднику быстро найти ваш аккаунт и
                        историю операций.
                    </p>
                </aside>
            </div>
        </main>
    </AuthenticatedLayout>
</template>
