<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const copied = ref(false);
const supportCode = computed(() => page.props.auth?.user?.support_code ?? '');

const copySupportCode = async () => {
    await navigator.clipboard.writeText(supportCode.value);
    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 2000);
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Поддержка" />

        <div class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8"
            >
                <p
                    class="text-sm font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400"
                >
                    Поддержка ZARQ
                </p>
                <h1
                    class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
                >
                    Код вашего аккаунта
                </h1>
                <p class="mt-3 max-w-2xl text-slate-600 dark:text-slate-300">
                    При обращении в поддержку сообщите этот код. По нему
                    сотрудник найдёт ваш аккаунт, операции и историю генераций.
                </p>

                <div
                    class="mt-6 flex flex-col gap-3 rounded-xl bg-slate-100 p-4 dark:bg-slate-950 sm:flex-row sm:items-center sm:justify-between"
                >
                    <code
                        class="break-all text-lg font-semibold tracking-wider text-slate-950 dark:text-white"
                        >{{ supportCode }}</code
                    >
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950"
                        @click="copySupportCode"
                    >
                        {{ copied ? 'Скопировано' : 'Скопировать код' }}
                    </button>
                </div>

                <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">
                    Форма обращений и переписка с поддержкой появятся здесь в
                    следующей версии.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
