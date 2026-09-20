<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const page = usePage();

const isSidebarOpen = ref(false);
const isSidebarCollapsed = ref(true);
const isProfileOpen = ref(false);
const isDark = ref(false);

const navigation = [
    { label: 'Главная', href: '/dashboard', icon: 'home' },
    { label: 'Мои карточки', href: '/history', icon: 'history' },
    { label: 'API интеграции', href: '/integrations', icon: 'integration' },
];

const secondaryNavigation = [
    { label: 'Инструкция', href: '/docs', icon: 'book' },
    { label: 'Поддержка', href: '/support', icon: 'support' },
];

const user = computed(() => page.props.auth?.user ?? {});
const userBalance = computed(
    () => user.value.zarq_balance ?? user.value.balance ?? 0,
);
const userInitial = computed(() =>
    String(user.value.name ?? 'Z')
        .trim()
        .charAt(0)
        .toUpperCase(),
);

const isActive = (href) => {
    return page.url === href || page.url.startsWith(`${href}/`);
};

const applyTheme = (dark) => {
    document.documentElement.classList.toggle('dark', dark);
};

const toggleTheme = () => {
    isDark.value = !isDark.value;
};

const closeSidebar = () => {
    isSidebarOpen.value = false;
};

const toggleDesktopSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
};

const closeMenusOnEscape = (event) => {
    if (event.key === 'Escape') {
        isSidebarOpen.value = false;
        isProfileOpen.value = false;
    }
};

watch(isDark, (dark) => {
    applyTheme(dark);
    localStorage.setItem('zarq-theme', dark ? 'dark' : 'light');
});

watch(isSidebarCollapsed, (collapsed) => {
    localStorage.setItem('zarq-sidebar-collapsed', String(collapsed));
});

onMounted(() => {
    const savedTheme = localStorage.getItem('zarq-theme');
    const savedSidebarState = localStorage.getItem('zarq-sidebar-collapsed');

    isDark.value = savedTheme
        ? savedTheme === 'dark'
        : window.matchMedia('(prefers-color-scheme: dark)').matches;
    isSidebarCollapsed.value =
        savedSidebarState === null ? true : savedSidebarState === 'true';

    applyTheme(isDark.value);
    document.addEventListener('keydown', closeMenusOnEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', closeMenusOnEscape);
});
</script>

<template>
    <div
        class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-white"
    >
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <button
                v-if="isSidebarOpen"
                type="button"
                class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden"
                aria-label="Закрыть меню"
                @click="closeSidebar"
            />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white px-4 py-5 transition-all duration-300 dark:border-slate-800 dark:bg-slate-900 lg:translate-x-0"
            :class="[
                isSidebarOpen ? 'translate-x-0' : '-translate-x-full',
                isSidebarCollapsed ? 'lg:w-20 lg:px-3' : 'lg:w-72 lg:px-4',
            ]"
        >
            <div
                class="flex h-10 items-center justify-between px-2"
                :class="isSidebarCollapsed ? 'lg:justify-center' : ''"
            >
                <Link
                    href="/dashboard"
                    class="group flex items-center gap-3"
                    aria-label="На главную"
                    @click="closeSidebar"
                >
                    <img
                        src="/assets/brand/zarqi-lightning-icon.svg"
                        alt=""
                        class="h-9 w-9 drop-shadow-[0_4px_6px_rgba(124,58,237,0.35)] transition-transform duration-200 group-hover:scale-110"
                    />
                    <span
                        class="text-xl font-black tracking-[0.18em] text-slate-950 dark:text-white"
                        :class="isSidebarCollapsed ? 'lg:hidden' : ''"
                        >ZARQ</span
                    >
                </Link>
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 lg:hidden"
                    aria-label="Закрыть меню"
                    @click="closeSidebar"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <button
                type="button"
                class="absolute -right-3 top-16 hidden h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-violet-300 hover:text-violet-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-violet-500 dark:hover:text-violet-300 lg:flex"
                :aria-label="
                    isSidebarCollapsed ? 'Развернуть меню' : 'Свернуть меню'
                "
                :title="
                    isSidebarCollapsed ? 'Развернуть меню' : 'Свернуть меню'
                "
                @click="toggleDesktopSidebar"
            >
                <svg
                    class="h-4 w-4 transition-transform"
                    :class="isSidebarCollapsed ? '' : 'rotate-180'"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="m9 18 6-6-6-6" />
                </svg>
            </button>

            <nav class="mt-7 flex flex-1 flex-col gap-1 overflow-y-auto">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    :title="isSidebarCollapsed ? item.label : undefined"
                    class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                    :class="
                        isActive(item.href)
                            ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'
                    "
                    @click="closeSidebar"
                >
                    <svg
                        v-if="item.icon === 'home'"
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1v-9Z"
                        />
                    </svg>
                    <svg
                        v-else-if="item.icon === 'history'"
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
                        <path d="M3 3v5h5M12 7v5l3 2" />
                    </svg>
                    <svg
                        v-else
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M8 12h8M12 8v8" />
                        <rect x="3" y="3" width="18" height="18" rx="5" />
                    </svg>
                    <span :class="isSidebarCollapsed ? 'lg:hidden' : ''">{{
                        item.label
                    }}</span>
                </Link>

                <div class="mt-auto flex flex-col gap-1">
                    <Link
                        v-for="item in secondaryNavigation"
                        :key="item.href"
                        :href="item.href"
                        :title="isSidebarCollapsed ? item.label : undefined"
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                        @click="closeSidebar"
                    >
                        <svg
                            v-if="item.icon === 'book'"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V4H6.5A2.5 2.5 0 0 0 4 6.5v13Z"
                            />
                            <path d="M4 19.5v-13" />
                        </svg>
                        <svg
                            v-else
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M4 13a8 8 0 0 1 16 0M4 13v5a2 2 0 0 0 2 2h2v-7H4Zm16 0v5a2 2 0 0 1-2 2h-2v-7h4Z"
                            />
                        </svg>
                        <span :class="isSidebarCollapsed ? 'lg:hidden' : ''">{{
                            item.label
                        }}</span>
                    </Link>

                    <button
                        type="button"
                        :title="
                            isSidebarCollapsed
                                ? isDark
                                    ? 'Тёмная тема'
                                    : 'Светлая тема'
                                : undefined
                        "
                        class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                        @click="toggleTheme"
                    >
                        <span class="flex items-center gap-3">
                            <svg
                                v-if="!isDark"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle cx="12" cy="12" r="4" />
                                <path
                                    d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"
                                />
                            </svg>
                            <svg
                                v-else
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"
                                />
                            </svg>
                            <span
                                :class="isSidebarCollapsed ? 'lg:hidden' : ''"
                                >{{ isDark ? 'Тёмная' : 'Светлая' }}</span
                            >
                        </span>
                        <span
                            class="relative h-6 w-11 rounded-full bg-slate-200 transition dark:bg-violet-600"
                            :class="isSidebarCollapsed ? 'lg:hidden' : ''"
                        >
                            <span
                                class="absolute top-1 h-4 w-4 rounded-full bg-white shadow transition-all"
                                :class="isDark ? 'left-6' : 'left-1'"
                            />
                        </span>
                    </button>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        :title="isSidebarCollapsed ? 'Выйти' : undefined"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-slate-600 transition hover:bg-red-50 hover:text-red-600 dark:text-slate-400 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"
                            />
                        </svg>
                        <span :class="isSidebarCollapsed ? 'lg:hidden' : ''"
                            >Выйти</span
                        >
                    </Link>
                </div>
            </nav>
        </aside>

        <div
            class="min-h-screen transition-[padding] duration-300"
            :class="isSidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'"
        >
            <header
                class="sticky top-0 z-30 flex h-20 items-center border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90 sm:px-6 lg:px-8"
            >
                <button
                    type="button"
                    class="mr-3 rounded-xl p-2.5 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden"
                    aria-label="Открыть меню"
                    @click="isSidebarOpen = true"
                >
                    <svg
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <slot name="header" />
                </div>

                <div class="ml-auto flex items-center gap-2 sm:gap-3">
                    <div
                        class="hidden items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-bold text-slate-800 dark:text-white sm:flex"
                        aria-label="Баланс ZARQ"
                    >
                        <img
                            src="/assets/brand/zarqi-lightning-icon.svg"
                            alt=""
                            class="h-7 w-7"
                        />
                        <span>{{ userBalance }} ZARQ</span>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl p-1.5 transition hover:bg-slate-100 dark:hover:bg-slate-800"
                            aria-haspopup="menu"
                            :aria-expanded="isProfileOpen"
                            @click="isProfileOpen = !isProfileOpen"
                        >
                            <span
                                class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 text-sm font-bold text-white"
                            >
                                <img
                                    v-if="user.avatar"
                                    :src="user.avatar"
                                    :alt="user.name"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ userInitial }}</span>
                            </span>
                            <span
                                class="hidden max-w-32 truncate text-sm font-semibold text-slate-800 dark:text-slate-100 md:block"
                                >{{ user.name }}</span
                            >
                            <svg
                                class="hidden h-4 w-4 text-slate-400 transition md:block"
                                :class="isProfileOpen ? 'rotate-180' : ''"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>

                        <button
                            v-if="isProfileOpen"
                            type="button"
                            class="fixed inset-0 z-40 cursor-default"
                            aria-label="Закрыть меню профиля"
                            @click="isProfileOpen = false"
                        />
                        <Transition
                            enter-active-class="transition duration-150"
                            enter-from-class="translate-y-1 opacity-0"
                            enter-to-class="translate-y-0 opacity-100"
                            leave-active-class="transition duration-100"
                            leave-from-class="translate-y-0 opacity-100"
                            leave-to-class="translate-y-1 opacity-0"
                        >
                            <div
                                v-if="isProfileOpen"
                                class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-slate-700 dark:bg-slate-800"
                                role="menu"
                            >
                                <div
                                    class="border-b border-slate-100 px-3 py-2.5 dark:border-slate-700"
                                >
                                    <p
                                        class="truncate text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ user.name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ user.email }}
                                    </p>
                                </div>
                                <Link
                                    href="/profile"
                                    class="mt-1 block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700"
                                    @click="isProfileOpen = false"
                                    >Профиль</Link
                                >
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    class="block w-full rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                                    >Выйти</Link
                                >
                            </div>
                        </Transition>
                    </div>
                </div>
            </header>

            <main
                class="min-h-[calc(100vh-5rem)] bg-slate-50 dark:bg-slate-950"
            >
                <slot />
            </main>
        </div>
    </div>
</template>
