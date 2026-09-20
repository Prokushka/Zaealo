<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
});

const page = usePage();
const form = useForm({});

const email = computed(() => page.props.auth?.user?.email ?? 'вашу почту');
const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);

const submit = () => {
    form.post(route('verification.send'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-8 sm:px-6"
    >
        <Head title="Подтверждение почты" />

        <main
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-white/10 dark:bg-slate-900 sm:p-8"
        >
            <header class="text-center">
                <div
                    class="font-['Inter'] text-3xl font-black leading-none tracking-[-0.05em] text-slate-950 dark:text-white"
                >
                    ZARQ
                </div>

                <div
                    class="mx-auto mt-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                    aria-hidden="true"
                >
                    <svg
                        class="h-7 w-7"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <rect x="3" y="5" width="18" height="14" rx="3" />
                        <path d="m4 7 8 6 8-6" />
                    </svg>
                </div>

                <h1
                    class="mt-5 text-2xl font-bold text-slate-950 dark:text-white"
                >
                    Подтвердите электронную почту
                </h1>

                <p
                    class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400"
                >
                    Мы отправили ссылку на
                    <span class="font-semibold text-slate-900 dark:text-white">
                        {{ email }}
                    </span>
                    . Перейдите по ней в течение 60 минут, чтобы открыть доступ
                    к ZARQ.
                </p>
            </header>

            <div
                v-if="verificationLinkSent"
                class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200"
                role="status"
            >
                Новая ссылка отправлена. Проверьте входящие письма и папку
                «Спам».
            </div>

            <form class="mt-6 grid gap-3" @submit.prevent="submit">
                <PrimaryButton
                    class="flex min-h-11 w-full justify-center"
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    {{
                        form.processing
                            ? 'Отправляем…'
                            : 'Отправить ссылку ещё раз'
                    }}
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex min-h-11 w-full items-center justify-center rounded-md px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white dark:focus:ring-offset-slate-900"
                >
                    Выйти из аккаунта
                </Link>
            </form>
        </main>
    </div>
</template>
