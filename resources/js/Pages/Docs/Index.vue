<script setup>
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const activeMarketplace = ref('ozon');
const selectedImage = ref(null);

const guides = {
    ozon: {
        name: 'Ozon',
        color: 'blue',
        intro: 'Для подключения понадобятся Client ID и API-ключ Seller API.',
        steps: [
            {
                title: 'Откройте настройки кабинета',
                description:
                    'Войдите в личный кабинет продавца Ozon и в меню «Настройки» выберите раздел API.',
                image: '/images/guides/ozon/01-open-settings.jpg',
            },
            {
                title: 'Перейдите в Seller API',
                description:
                    'Откройте блок Seller API — здесь управляют ключами для интеграций.',
                image: '/images/guides/ozon/02-open-seller-api.jpg',
            },
            {
                title: 'Начните создание ключа',
                description:
                    'Нажмите «Сгенерировать ключ», чтобы создать отдельный ключ для ZARQ.',
                image: '/images/guides/ozon/03-generate-key.jpg',
            },
            {
                title: 'Укажите параметры ключа',
                description:
                    'Введите понятное название, например «ZARQ», чтобы легко отличить ключ от других интеграций.',
                image: '/images/guides/ozon/04-key-settings.jpg',
            },
            {
                title: 'Выберите права доступа',
                description:
                    'Разрешите чтение данных каталога и товаров. Если в кабинете включены ограничения IP, добавьте IP вашего сервера.',
                image: '/images/guides/ozon/05-token-roles.jpg',
            },
            {
                title: 'Скопируйте API-ключ',
                description:
                    'Сохраните ключ сразу после создания: Ozon показывает его только один раз. Затем вставьте ключ и Client ID в интеграцию ZARQ.',
                image: '/images/guides/ozon/06-copy-key.jpg',
            },
        ],
    },
    wildberries: {
        name: 'Wildberries',
        color: 'violet',
        intro: 'Для подключения нужен токен API из кабинета продавца Wildberries.',
        steps: [
            {
                title: 'Откройте раздел API-интеграции',
                description:
                    'В личном кабинете Wildberries перейдите в «Настройки» → «Доступ к API».',
                image: '/images/guides/wildberries/01-open-api-integrations.jpg',
            },
            {
                title: 'Создайте новый токен',
                description:
                    'Нажмите «Создать токен», чтобы выпустить отдельный ключ для ZARQ.',
                image: '/images/guides/wildberries/02-create-token.jpg',
            },
            {
                title: 'Выберите ручную интеграцию',
                description:
                    'Выберите вариант ручной интеграции, чтобы самостоятельно настроить права доступа.',
                image: '/images/guides/wildberries/03-manual-integration.jpg',
            },
            {
                title: 'Выберите тип токена',
                description:
                    'Выберите стандартный токен для работы с товарами и карточками.',
                image: '/images/guides/wildberries/04-basic-token.jpg',
            },
            {
                title: 'Выдайте нужные разрешения',
                description:
                    'Включите «Контент», «Маркетплейс» и «Цены и скидки», чтобы ZARQ мог получать данные карточек.',
                image: '/images/guides/wildberries/05-permissions.jpg',
            },
            {
                title: 'Назовите токен',
                description:
                    'Укажите название «ZARQ» или другое понятное имя, затем подтвердите создание.',
                image: '/images/guides/wildberries/06-name-token.jpg',
            },
            {
                title: 'Скопируйте токен',
                description:
                    'Скопируйте токен сразу после создания и вставьте его в интеграцию ZARQ.',
                image: '/images/guides/wildberries/07-copy-token.jpg',
            },
        ],
    },
};

const guide = computed(() => guides[activeMarketplace.value]);

const openImage = (step) => {
    selectedImage.value = step;
};
</script>

<template>
    <div>
        <Head title="Инструкция" />

        <AuthenticatedLayout>
            <template #header>
                <div>
                    <p
                        class="text-sm font-medium text-indigo-600 dark:text-indigo-400"
                    >
                        Инструкция
                    </p>
                    <h1
                        class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white"
                    >
                        Подключение API-ключа
                    </h1>
                </div>
            </template>

            <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <section
                    class="rounded-2xl border border-indigo-100 bg-indigo-50 p-5 dark:border-indigo-900/60 dark:bg-indigo-500 sm:p-6"
                >
                    <h2
                        class="text-lg font-semibold text-indigo-950 dark:text-indigo-100"
                    >
                        Создайте отдельный ключ для ZARQ
                    </h2>
                    <p
                        class="mt-2 max-w-3xl text-sm leading-6 text-indigo-900/80 dark:text-indigo-200/80"
                    >
                        Следуйте шагам для нужного маркетплейса. Не передавайте
                        API-ключ в чатах и не публикуйте его: после создания
                        вставьте его только в настройках интеграции.
                    </p>
                </section>

                <div
                    class="mt-8 inline-flex rounded-xl bg-gray-100 p-1 dark:bg-gray-800"
                    role="tablist"
                    aria-label="Маркетплейс"
                >
                    <button
                        v-for="(marketplace, key) in guides"
                        :key="key"
                        type="button"
                        role="tab"
                        :aria-selected="activeMarketplace === key"
                        class="rounded-lg px-5 py-2.5 text-sm font-semibold transition"
                        :class="
                            activeMarketplace === key
                                ? marketplace.color === 'blue'
                                    ? 'bg-blue-600 text-white shadow-sm'
                                    : 'bg-violet-600 text-white shadow-sm'
                                : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white'
                        "
                        @click="activeMarketplace = key"
                    >
                        {{ marketplace.name }}
                    </button>
                </div>

                <section
                    class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <div
                        class="border-b px-5 py-5 sm:px-7"
                        :class="
                            guide.color === 'blue'
                                ? 'border-blue-100 bg-blue-50/60 dark:border-blue-900/50 dark:bg-blue-950/20'
                                : 'border-violet-100 bg-violet-50/60 dark:border-violet-900/50 dark:bg-violet-950/20'
                        "
                    >
                        <h2
                            class="text-xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ guide.name }}
                        </h2>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-300"
                        >
                            {{ guide.intro }}
                        </p>
                    </div>

                    <ol class="grid gap-6 p-5 sm:p-7">
                        <li
                            v-for="(step, index) in guide.steps"
                            :key="step.image"
                            class="grid gap-4 lg:grid-cols-[3rem_minmax(0,1fr)]"
                        >
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full text-base font-bold text-white"
                                :class="
                                    guide.color === 'blue'
                                        ? 'bg-blue-600'
                                        : 'bg-violet-600'
                                "
                            >
                                {{ index + 1 }}
                            </div>
                            <article
                                class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50"
                            >
                                <div class="p-5">
                                    <h3
                                        class="text-base font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{ step.title }}
                                    </h3>
                                    <p
                                        class="mt-1.5 text-sm leading-6 text-gray-600 dark:text-gray-300"
                                    >
                                        {{ step.description }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="group block w-full border-t border-gray-200 bg-white text-left dark:border-gray-700 dark:bg-gray-900"
                                    :aria-label="`Открыть изображение: ${step.title}`"
                                    @click="openImage(step)"
                                >
                                    <img
                                        :src="step.image"
                                        :alt="`${guide.name}: ${step.title}`"
                                        class="aspect-video w-full object-cover transition duration-200 group-hover:opacity-90"
                                        loading="lazy"
                                    />
                                    <span
                                        class="block px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400"
                                        >Нажмите на изображение, чтобы
                                        увеличить</span
                                    >
                                </button>
                            </article>
                        </li>
                    </ol>
                </section>
            </main>
        </AuthenticatedLayout>

        <Modal
            :show="selectedImage !== null"
            max-width="xl"
            @close="selectedImage = null"
        >
            <div v-if="selectedImage" class="p-4 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ selectedImage.title }}
                    </h2>
                    <button
                        type="button"
                        class="text-sm font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                        @click="selectedImage = null"
                    >
                        Закрыть
                    </button>
                </div>
                <img
                    :src="selectedImage.image"
                    :alt="selectedImage.title"
                    class="mt-4 w-full rounded-lg"
                />
            </div>
        </Modal>
    </div>
</template>
