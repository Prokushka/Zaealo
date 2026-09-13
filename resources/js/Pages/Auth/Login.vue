<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ canResetPassword: Boolean, status: String });

const mode = ref('login');
const loginForm = useForm({ email: '', password: '', remember: false });
const registerForm = useForm({
    name: '',
    email: '',
    password: '',
});

const submitLogin = () =>
    loginForm.post(route('login'), {
        onFinish: () => loginForm.reset('password'),
    });
const submitRegistration = () =>
    registerForm.post(route('register'), {
        onFinish: () => registerForm.reset('password'),
    });
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-gray-950 px-4 py-8 sm:px-6"
    >
        <Head :title="mode === 'login' ? 'Вход' : 'Регистрация'" />

        <div
            class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-white/10 dark:bg-gray-900 sm:p-8"
        >
            <header class="mb-6 text-center">
                <div
                    class="font-['Inter'] text-3xl font-black leading-none tracking-[-0.05em] dark:text-white"
                >
                    ZARQ
                </div>
            </header>

            <div
                class="mb-6 grid grid-cols-2 gap-1 rounded-xl bg-gray-100 p-1 dark:bg-gray-800"
            >
                <button
                    v-for="item in [
                        ['login', 'Вход'],
                        ['register', 'Регистрация'],
                    ]"
                    :key="item[0]"
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="
                        mode === item[0]
                            ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-700 dark:text-white'
                            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                    "
                    @click="mode = item[0]"
                >
                    {{ item[1] }}
                </button>
            </div>

            <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
                {{ status }}
            </div>

            <div class="grid gap-3">
                <a
                    :href="route('yandex.redirect')"
                    class="flex min-h-12 items-center justify-center gap-3 rounded-xl bg-black px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-gray-800 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-2 dark:bg-white dark:text-black dark:hover:bg-gray-100 dark:focus:ring-offset-gray-900"
                >
                    <span
                        class="flex h-7 w-7 items-center justify-center rounded-full bg-[#FC3F1D]"
                        aria-hidden="true"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none">
                            <path
                                fill="white"
                                d="M13.47 3.25h-1.15c-2.84 0-5.1 1.72-5.1 4.55 0 2.18 1.05 3.55 2.92 4.88L6.8 18.72c-.12.22 0 .28.18.28h2.14c.17 0 .29-.05.36-.2l3.03-5.72h1.08v5.72c0 .12.08.2.2.2h1.87c.13 0 .2-.08.2-.2V3.45c0-.12-.07-.2-.2-.2h-2.19Zm.12 8.02h-.84c-1.68 0-3.12-.76-3.12-3.32 0-2.66 1.58-2.89 3.12-2.89h.84v6.21Z"
                            />
                        </svg>
                    </span>
                    Продолжить с Яндекс ID
                </a>
                <InputError :message="$page.props.errors.yandex" />

                <a
                    :href="route('telegram.redirect')"
                    class="flex min-h-12 items-center justify-center gap-3 rounded-xl bg-[#229ED9] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#1d8fc4] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#229ED9] focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                >
                    <svg viewBox="0 0 24 24" class="h-7 w-7" aria-hidden="true">
                        <circle cx="12" cy="12" r="12" fill="white" />
                        <path
                            fill="#229ED9"
                            d="m18.42 6.28-2.15 10.13c-.16.72-.59.9-1.19.56l-3.27-2.41-1.58 1.52c-.17.17-.32.32-.66.32l.24-3.33 6.06-5.48c.26-.23-.06-.36-.41-.13l-7.49 4.72-3.22-1.01c-.7-.22-.71-.7.15-1.04l12.59-4.85c.58-.21 1.09.14.93 1Z"
                        />
                    </svg>
                    Продолжить с Telegram
                </a>
                <InputError :message="$page.props.errors.telegram" />
            </div>

            <div class="my-6 flex items-center gap-3">
                <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
                <span class="text-xs text-gray-400">или по почте</span>
                <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
            </div>

            <form v-if="mode === 'login'" @submit.prevent="submitLogin">
                <InputLabel for="login-email" value="Электронная почта" />
                <TextInput
                    id="login-email"
                    v-model="loginForm.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="loginForm.errors.email" />

                <div class="mt-4">
                    <InputLabel for="login-password" value="Пароль" />
                    <TextInput
                        id="login-password"
                        v-model="loginForm.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="current-password"
                    />
                    <InputError
                        class="mt-2"
                        :message="loginForm.errors.password"
                    />
                </div>

                <div class="mt-4 flex items-center justify-between gap-4">
                    <label class="flex items-center">
                        <Checkbox
                            v-model:checked="loginForm.remember"
                            name="remember"
                        />
                        <span
                            class="ms-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            Запомнить меня
                        </span>
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white"
                    >
                        Забыли пароль?
                    </Link>
                </div>

                <PrimaryButton
                    class="mt-6 flex min-h-11 w-full justify-center"
                    :class="{ 'opacity-50': loginForm.processing }"
                    :disabled="loginForm.processing"
                >
                    Войти
                </PrimaryButton>
            </form>

            <form v-else @submit.prevent="submitRegistration">
                <div
                    v-for="field in [
                        ['name', 'Имя', 'text', 'name'],
                        ['email', 'Электронная почта', 'email', 'username'],
                        ['password', 'Пароль', 'password', 'new-password'],
                    ]"
                    :key="field[0]"
                    class="mt-4 first:mt-0"
                >
                    <InputLabel
                        :for="`register-${field[0]}`"
                        :value="field[1]"
                    />
                    <TextInput
                        :id="`register-${field[0]}`"
                        v-model="registerForm[field[0]]"
                        :type="field[2]"
                        class="mt-1 block w-full"
                        required
                        :autocomplete="field[3]"
                    />
                    <InputError
                        class="mt-2"
                        :message="registerForm.errors[field[0]]"
                    />
                </div>

                <PrimaryButton
                    class="mt-6 flex min-h-11 w-full justify-center"
                    :class="{ 'opacity-50': registerForm.processing }"
                    :disabled="registerForm.processing"
                >
                    Создать аккаунт
                </PrimaryButton>
            </form>
        </div>
    </div>
</template>
