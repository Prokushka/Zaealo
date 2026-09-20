<script setup>
import CardBuilderWizard from '@/Components/CardBuilder/CardBuilderWizard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const cardBuilderWizard = ref(null);
const analysisProcessing = ref(false);
const competitorSearchProcessing = ref(false);
const aiPhotoProcessing = ref(false);
const archiveProcessing = ref(false);
const publicationProcessing = ref(false);
const saveProcessing = ref(false);
const textRegenerationProcessing = ref(false);

const props = defineProps({
    emailVerified: {
        type: Boolean,
        default: false,
    },
    card: {
        type: Object,
        default: () => ({
            id: null,
            title: '',
            description: '',
            marketplace: null,
            allowed_photo_slots: 3,
            images: [],
            is_editing: false,
        }),
    },
    pricing: {
        type: Object,
        default: () => ({
            cost_export: 25,
            cost_extra_slots: 5,
            cost_image_generation: 50,
            cost_text_regeneration: 25,
            cost_pro_copywriting: 50,
        }),
    },
});

const analyzeCard = (cardData) => {
    router.post(
        route('card-analyses.store'),
        {
            marketplace: cardData.marketplace,
            generation_mode: cardData.generationMode,
            copywriting_quality: cardData.copywritingQuality,
            photos: cardData.images
                .filter((image) => image.file)
                .map((image) => image.file),
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => (analysisProcessing.value = true),
            onSuccess: (page) => {
                const result = page.props.card_analysis;

                if (result?.status === 'questionnaire') {
                    cardBuilderWizard.value?.showQuestionnaire(result);
                } else if (result?.status === 'completed') {
                    cardBuilderWizard.value?.completeAnalysis(result);
                }
            },
            onError: (errors) =>
                cardBuilderWizard.value?.showAnalysisError(errors),
            onFinish: () => (analysisProcessing.value = false),
        },
    );
};

const completeAnalysis = ({ analysisId, answers }) => {
    router.post(
        route('card-analyses.complete', analysisId),
        { answers },
        {
            preserveScroll: true,
            onStart: () => (analysisProcessing.value = true),
            onSuccess: (page) => {
                const result = page.props.card_analysis;

                if (result?.status === 'completed') {
                    cardBuilderWizard.value?.completeAnalysis(result);
                }
            },
            onError: (errors) =>
                cardBuilderWizard.value?.showAnalysisError(errors),
            onFinish: () => (analysisProcessing.value = false),
        },
    );
};

const searchCompetitors = ({ marketplaceSearchQuery, marketplace }) => {
    router.post(
        route('competitor-cards.search'),
        {
            marketplace_search_query: marketplaceSearchQuery,
            marketplace,
        },
        {
            preserveScroll: true,
            onStart: () => (competitorSearchProcessing.value = true),
            onFinish: () => (competitorSearchProcessing.value = false),
        },
    );
};

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const generateAiPhotos = async ({
    analysisId,
    scenarios,
    infographicFeatures,
}) => {
    if (props.card.is_editing) {
        aiPhotoProcessing.value = true;

        try {
            const response = await fetch(
                route('cards.regenerate-images', analysisId),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        scenarios,
                        infographic_features: infographicFeatures,
                    }),
                },
            );
            await responseJson(response);
            cardBuilderWizard.value?.queueAiPhotoGeneration();
            refreshBalance();
        } catch (error) {
            cardBuilderWizard.value?.showAnalysisError(
                error instanceof Error
                    ? error.message
                    : 'Не удалось запустить генерацию фото.',
            );
        } finally {
            aiPhotoProcessing.value = false;
        }

        return;
    }

    router.post(
        route('card-generations.images.store', analysisId),
        {
            scenarios,
            infographic_features: infographicFeatures,
        },
        {
            preserveScroll: true,
            onStart: () => (aiPhotoProcessing.value = true),
            onSuccess: () => cardBuilderWizard.value?.queueAiPhotoGeneration(),
            onError: (errors) =>
                cardBuilderWizard.value?.showAnalysisError(errors),
            onFinish: () => (aiPhotoProcessing.value = false),
        },
    );
};

const saveCard = async ({ analysisId, title, description }) => {
    if (!analysisId || saveProcessing.value) {
        return;
    }

    saveProcessing.value = true;

    try {
        const response = await fetch(route('cards.update', analysisId), {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ title, description }),
        });
        const result = await responseJson(response);

        cardBuilderWizard.value?.markCardSaved(result.message);
    } catch (error) {
        cardBuilderWizard.value?.showEditError(
            error instanceof Error
                ? error.message
                : 'Не удалось сохранить изменения.',
        );
    } finally {
        saveProcessing.value = false;
    }
};

const regenerateText = async ({ analysisId }) => {
    if (!analysisId || textRegenerationProcessing.value) {
        return;
    }

    textRegenerationProcessing.value = true;

    try {
        const response = await fetch(
            route('cards.regenerate-text', analysisId),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({}),
            },
        );
        const result = await responseJson(response);

        router.visit(route('dashboard', { card_id: result.generation_id }));
    } catch (error) {
        cardBuilderWizard.value?.showEditError(
            error instanceof Error
                ? error.message
                : 'Не удалось перегенерировать текст.',
        );
        textRegenerationProcessing.value = false;
    }
};

const responseJson = async (response) => {
    const body = await response.text();
    let result;

    try {
        result = JSON.parse(body);
    } catch {
        throw new Error(
            'Сервер вернул некорректный ответ. Попробуйте ещё раз.',
        );
    }

    if (!response.ok) {
        const validationMessage = Object.values(result.errors ?? {})
            .flat()
            .find((message) => typeof message === 'string');

        throw new Error(
            validationMessage ??
                result.message ??
                'Не удалось экспортировать карточку.',
        );
    }

    return result;
};

const downloadArchive = async ({ analysisId }) => {
    if (!analysisId || archiveProcessing.value) {
        return;
    }

    archiveProcessing.value = true;

    try {
        const response = await fetch(
            route('card-generations.exports.archive', analysisId),
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

        cardBuilderWizard.value?.markArchivePrepared();

        const download = document.createElement('a');
        download.href = result.download_url;
        download.download = '';
        document.body.appendChild(download);
        download.click();
        download.remove();

        router.reload({
            only: ['auth'],
            preserveScroll: true,
            preserveState: true,
        });
    } catch (error) {
        cardBuilderWizard.value?.showExportError(
            error instanceof Error
                ? error.message
                : 'Не удалось экспортировать карточку.',
        );
    } finally {
        archiveProcessing.value = false;
    }
};

const publishCard = async ({ analysisId, publication }) => {
    if (!analysisId || publicationProcessing.value) {
        return;
    }

    publicationProcessing.value = true;

    try {
        const response = await fetch(
            route('card-generations.exports.publish', analysisId),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content'),
                },
                body: JSON.stringify(publication),
            },
        );
        const result = await responseJson(response);

        cardBuilderWizard.value?.beginPublication(result);
    } catch (error) {
        cardBuilderWizard.value?.showExportError(
            error instanceof Error
                ? error.message
                : 'Не удалось загрузить черновик в маркетплейс.',
        );
    } finally {
        publicationProcessing.value = false;
    }
};

const refreshBalance = () => {
    router.reload({
        only: ['auth'],
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head
        :title="props.card.is_editing ? 'Редактирование карточки' : 'Главная'"
    />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="truncate text-lg font-bold text-slate-900 dark:text-white"
            >
                {{
                    props.card.is_editing
                        ? 'Редактирование карточки'
                        : 'Главная'
                }}
            </h2>
        </template>

        <div
            v-if="emailVerified"
            class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200"
            role="status"
        >
            Электронная почта подтверждена. Теперь вам доступны все возможности
            ZARQ.
        </div>

        <CardBuilderWizard
            ref="cardBuilderWizard"
            :card="card"
            :pricing="pricing"
            :processing="analysisProcessing"
            :competitor-search-processing="competitorSearchProcessing"
            :ai-photo-processing="aiPhotoProcessing"
            :archive-processing="archiveProcessing"
            :publication-processing="publicationProcessing"
            :save-processing="saveProcessing"
            :text-regeneration-processing="textRegenerationProcessing"
            @analyze="analyzeCard"
            @complete-analysis="completeAnalysis"
            @search-competitors="searchCompetitors"
            @generate-ai-photos="generateAiPhotos"
            @save-card="saveCard"
            @regenerate-text="regenerateText"
            @ai-photo-settled="refreshBalance"
            @download-archive="downloadArchive"
            @publish="publishCard"
        />
    </AuthenticatedLayout>
</template>
