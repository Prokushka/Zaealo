<script setup>
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { Link } from '@inertiajs/vue3';
import QuestionnaireDialog from '@/Components/CardBuilder/QuestionnaireDialog.vue';
import StepNavigation from '@/Components/CardBuilder/StepNavigation.vue';
import AttributesStep from '@/Components/CardBuilder/steps/AttributesStep.vue';
import AiPhotosStep from '@/Components/CardBuilder/steps/AiPhotosStep.vue';
import ExportStep from '@/Components/CardBuilder/steps/ExportStep.vue';
import SeoTextStep from '@/Components/CardBuilder/steps/SeoTextStep.vue';

const props = defineProps({
    card: {
        type: Object,
        required: true,
    },
    pricing: {
        type: Object,
        required: true,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    competitorSearchProcessing: {
        type: Boolean,
        default: false,
    },
    aiPhotoProcessing: {
        type: Boolean,
        default: false,
    },
    archiveProcessing: {
        type: Boolean,
        default: false,
    },
    publicationProcessing: {
        type: Boolean,
        default: false,
    },
    saveProcessing: {
        type: Boolean,
        default: false,
    },
    textRegenerationProcessing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'analyze',
    'complete-analysis',
    'download-archive',
    'publish',
    'search-competitors',
    'generate-ai-photos',
    'ai-photo-settled',
    'save-card',
    'regenerate-text',
]);
const maximumPhotoCount = 5;
const maximumTotalImageSizeInMegabytes = 5;
const maximumTotalImageSizeInBytes =
    maximumTotalImageSizeInMegabytes * 1024 * 1024;
const marketplaceStorageKey = 'zarq-card-marketplace';

const steps = [
    { number: 1, title: 'Исходники', shortTitle: 'Исходники' },
    { number: 2, title: 'ИИ-Фото', shortTitle: 'Фото' },
    { number: 3, title: 'SEO-текст', shortTitle: 'SEO' },
    { number: 4, title: 'Категория', shortTitle: 'Категория' },
    { number: 5, title: 'Экспорт', shortTitle: 'Экспорт' },
];
const aiPhotoCategories = [
    {
        id: 'hero',
        title: 'Главное',
        description: 'Основной кадр для карточки товара.',
        options: [
            {
                id: 'clean-background',
                title: 'Белая/чистая заливка',
                description: 'Товар по центру, строго 1:1 для карточки.',
            },
            {
                id: 'model-background',
                title: 'С модельным фоном',
                description:
                    'Цветная подложка под товар или упаковку для контраста.',
            },
            {
                id: 'in-hand',
                title: 'В руках',
                description:
                    'Крупный план товара в ладони для оценки масштаба.',
            },
        ],
    },
    {
        id: 'lifestyle',
        title: 'Лайфстайл',
        description: 'Товар в естественном контексте использования.',
        options: [
            {
                id: 'environment',
                title: 'Среда обитания',
                description:
                    'Товар находится по назначению: кухня, полка или офис.',
            },
            {
                id: 'hands-in-use',
                title: 'В использовании (руки)',
                description:
                    'Человек держит или применяет товар — без лица или с нейтральным лицом.',
            },
            {
                id: 'emotion-scene',
                title: 'Эмоция/Сцена',
                description:
                    'Полная картинка с человеком, интерьером, улицей или природой.',
            },
            {
                id: 'flat-lay',
                title: 'Сетап (Flat lay)',
                description:
                    'Товар в окружении сопутствующих предметов, например кофе и ноутбука.',
            },
        ],
    },
    {
        id: 'features',
        title: 'Характеристики',
        description: 'Ракурсы для демонстрации деталей товара.',
        options: [
            {
                id: 'macro-details',
                title: 'Макро/Детали',
                description: 'Крупно текстура, шов, материал или застёжка.',
            },
            {
                id: 'size-comparison',
                title: 'Сравнение размеров',
                description: 'Товар рядом с линейкой, монетой или телефоном.',
            },
            {
                id: 'contents',
                title: 'Комплектация',
                description: 'Всё содержимое коробки разложено веером.',
            },
        ],
    },
    {
        id: 'infographics',
        title: 'Инфографика',
        description: 'Кадры с местом для полезного текста.',
        options: [
            {
                id: 'diagram',
                title: 'Схема',
                description:
                    'Чистый товар с цифровыми указателями для подписей материалов.',
            },
            {
                id: 'text-space',
                title: 'Плашка',
                description:
                    'Товар с большим пустым пространством слева или справа для размера и цены.',
            },
            {
                id: 'before-after',
                title: 'До/После',
                description:
                    'Два состояния товара: сложен/разложен или сухой/влажный.',
            },
        ],
    },
    {
        id: 'packaging',
        title: 'Упаковка и брендинг',
        description: 'Кадры, которые подчёркивают ценность подарка.',
        options: [
            {
                id: 'unboxing',
                title: 'Распаковка',
                description: 'Процесс открывания коробки, создающий ожидание.',
            },
            {
                id: 'gift-ready',
                title: 'Подарочный вид',
                description: 'Товар в фирменной упаковке или лентах.',
            },
        ],
    },
];

const normalizeImages = (images) => {
    if (!Array.isArray(images)) {
        return [];
    }

    return images.slice(0, maximumPhotoCount).map((image, index) => ({
        id: image?.id ?? `image-${index}`,
        url:
            typeof image === 'string'
                ? image
                : (image?.url ?? image?.src ?? ''),
        name:
            typeof image === 'string'
                ? `Фото ${index + 1}`
                : (image?.name ?? `Фото ${index + 1}`),
        size: typeof image === 'string' ? 0 : Number(image?.size ?? 0),
        isLocal: false,
    }));
};

const normalizeMarketplace = (marketplace) => {
    return ['ozon', 'wildberries'].includes(marketplace) ? marketplace : null;
};

const normalizeInfographicFeatures = (features) => {
    if (!Array.isArray(features)) {
        return [];
    }

    return features.filter(
        (feature) => typeof feature === 'string' && feature.trim(),
    );
};

const currentStep = ref(1);
const maximumAccessibleStep = ref(1);
const hasAnalyzed = ref(false);
const fileInput = ref(null);
const uploadError = ref('');
const analysisError = ref('');
const questionnaire = ref(null);
const questionnaireAnalysisId = ref(null);
const questionnaireAnswers = ref({});
const completedAnalysisId = ref(null);
const expandedAiPhotoCategoryId = ref(null);
const generatedAiPhotos = ref([]);
const marketplaceApiKeyStatuses = ref({
    wildberries: false,
    ozon: false,
});
const aiPhotoGenerationSummary = ref({
    total: 0,
    queued: 0,
    processing: 0,
    completed: 0,
    failed: 0,
});
const exportError = ref('');
const exportState = ref(null);
const archivePrepared = ref(false);
const editMessage = ref('');
const editError = ref('');
const localImageUrls = new Set();
let aiPhotoPollingTimer = null;
let exportPollingTimer = null;

const form = reactive({
    id: props.card.id,
    title: props.card.title ?? '',
    description: props.card.description ?? '',
    marketplaceSearchQuery: props.card.marketplace_search_query ?? '',
    marketplace: normalizeMarketplace(props.card.marketplace),
    generationMode:
        props.card.generation_mode === 'quick' ? 'quick' : 'standard',
    copywritingQuality:
        props.card.copywriting_quality === 'pro' ? 'pro' : 'standard',
    useCompetitorCards:
        typeof props.card.use_competitor_cards === 'boolean'
            ? props.card.use_competitor_cards
            : null,
    categoryMatch: props.card.category_match ?? null,
    selectedCategory: null,
    attributes: {},
    images: normalizeImages(props.card.images),
    aiPhotoScenarios: [],
    infographicFeatures: normalizeInfographicFeatures(
        props.card.infographic_features,
    ),
});

const isEditing = computed(() => props.card.is_editing === true);

const photoCount = computed(() => form.images.length);
const totalImageSizeInBytes = computed(() =>
    form.images.reduce(
        (total, image) => total + Number(image.size ?? image.file?.size ?? 0),
        0,
    ),
);
const totalImageSizeInMegabytes = computed(() =>
    (totalImageSizeInBytes.value / 1024 / 1024).toFixed(1),
);
const descriptionWordCount = computed(() => {
    const description = form.description.trim();

    return description ? description.split(/\s+/u).length : 0;
});
const marketplaceName = computed(() => {
    const names = {
        wildberries: 'Wildberries',
        ozon: 'Ozon',
    };

    return names[form.marketplace] ?? 'Не выбран';
});
const selectedAiPhotoScenarios = computed(() =>
    aiPhotoCategories.flatMap((category) =>
        category.options
            .filter((option) => form.aiPhotoScenarios.includes(option.id))
            .map((option) => ({
                category: category.id,
                subcategory: option.id,
            })),
    ),
);
const hasPendingAiPhotos = computed(
    () =>
        aiPhotoGenerationSummary.value.queued > 0 ||
        aiPhotoGenerationSummary.value.processing > 0,
);
const hasMarketplaceApiKey = computed(
    () => marketplaceApiKeyStatuses.value[form.marketplace] === true,
);
const completedGeneratedPhotoCount = computed(
    () => aiPhotoGenerationSummary.value.completed,
);
const effectiveExportCost = computed(() =>
    completedGeneratedPhotoCount.value > 0 || archivePrepared.value
        ? 0
        : Number(props.pricing.cost_export ?? 0),
);
const refreshMarketplaceApiKeyStatus = async (marketplace) => {
    if (!['wildberries', 'ozon'].includes(marketplace)) {
        return;
    }

    try {
        const response = await fetch(
            route('integrations.status', marketplace),
            {
                headers: { Accept: 'application/json' },
            },
        );

        if (!response.ok) {
            throw new Error('Не удалось проверить API-интеграцию.');
        }

        const integration = await response.json();

        marketplaceApiKeyStatuses.value[marketplace] =
            integration.has_key === true;
        form.useCompetitorCards = null;
    } catch {
        marketplaceApiKeyStatuses.value[marketplace] = false;
    }
};
const markMarketplaceApiKeySaved = (marketplace) => {
    marketplaceApiKeyStatuses.value[marketplace] = true;
    form.useCompetitorCards = null;
};
const canGoNext = computed(() => {
    if (currentStep.value >= steps.length || props.competitorSearchProcessing) {
        return false;
    }

    if (currentStep.value === 1) {
        return hasAnalyzed.value;
    }

    return true;
});
const goToStep = (step) => {
    const requestedStep = step === 4 && !hasMarketplaceApiKey.value ? 5 : step;

    if (
        requestedStep > maximumAccessibleStep.value ||
        (requestedStep === 1 && maximumAccessibleStep.value >= 2)
    ) {
        return;
    }

    currentStep.value = Math.min(Math.max(requestedStep, 1), steps.length);
};

const goNext = () => {
    if (!canGoNext.value) {
        return;
    }

    const nextStep =
        currentStep.value === 3 && !hasMarketplaceApiKey.value
            ? 5
            : currentStep.value + 1;
    maximumAccessibleStep.value = Math.max(
        maximumAccessibleStep.value,
        nextStep,
    );
    goToStep(nextStep);
};

const goBack = () => {
    goToStep(currentStep.value - 1);
};

const selectAiPhotoCategory = (categoryId) => {
    expandedAiPhotoCategoryId.value =
        expandedAiPhotoCategoryId.value === categoryId ? null : categoryId;
};

const selectAiPhotoScenario = (optionId) => {
    form.aiPhotoScenarios = form.aiPhotoScenarios.includes(optionId)
        ? form.aiPhotoScenarios.filter(
              (selectedOptionId) => selectedOptionId !== optionId,
          )
        : [...form.aiPhotoScenarios, optionId];
    expandedAiPhotoCategoryId.value = null;
};

const openFilePicker = () => {
    if (photoCount.value >= maximumPhotoCount) {
        uploadError.value = `Можно загрузить не более ${maximumPhotoCount} фотографий.`;

        return;
    }

    if (totalImageSizeInBytes.value >= maximumTotalImageSizeInBytes) {
        uploadError.value = `Общий лимит ${maximumTotalImageSizeInMegabytes} МБ уже использован.`;

        return;
    }

    fileInput.value?.click();
};

const addImages = (event) => {
    const files = Array.from(event.target.files ?? []);
    const acceptedFiles = [];
    let rejectedByCount = 0;
    let rejectedBySize = 0;
    let accumulatedCount = photoCount.value;
    let accumulatedSize = totalImageSizeInBytes.value;

    files.forEach((file) => {
        if (accumulatedCount >= maximumPhotoCount) {
            rejectedByCount += 1;
        } else if (accumulatedSize + file.size > maximumTotalImageSizeInBytes) {
            rejectedBySize += 1;
        } else {
            acceptedFiles.push(file);
            accumulatedCount += 1;
            accumulatedSize += file.size;
        }
    });

    const uploadErrors = [];

    if (rejectedByCount) {
        uploadErrors.push(
            `По количеству не добавлено: ${rejectedByCount}. Максимум — ${maximumPhotoCount} фото.`,
        );
    }

    if (rejectedBySize) {
        uploadErrors.push(
            `По размеру не добавлено: ${rejectedBySize}. Общий лимит — ${maximumTotalImageSizeInMegabytes} МБ.`,
        );
    }

    uploadError.value = uploadErrors.join(' ');

    acceptedFiles.forEach((file, index) => {
        const url = URL.createObjectURL(file);
        localImageUrls.add(url);
        form.images.push({
            id: `local-image-${Date.now()}-${index}`,
            url,
            name: file.name,
            size: file.size,
            file,
            isLocal: true,
        });
    });

    event.target.value = '';
};

const removeImage = (index) => {
    const [removedImage] = form.images.splice(index, 1);

    if (removedImage?.isLocal && removedImage.url) {
        URL.revokeObjectURL(removedImage.url);
        localImageUrls.delete(removedImage.url);
    }

    uploadError.value = '';
};

const requestAnalysis = () => {
    if (props.processing || photoCount.value === 0 || !form.marketplace) {
        return;
    }

    analysisError.value = '';
    emit('analyze', { ...form });
};

const showQuestionnaire = (result) => {
    analysisError.value = '';
    questionnaire.value = result.questionnaire;
    questionnaireAnalysisId.value = result.analysis_id;
    questionnaireAnswers.value = {};
};

const submitQuestionnaire = () => {
    if (props.processing) {
        return;
    }

    analysisError.value = '';
    emit('complete-analysis', {
        analysisId: questionnaireAnalysisId.value,
        answers: questionnaireAnswers.value,
    });
};

const completeAnalysis = (result) => {
    analysisError.value = '';
    if (
        result.analysis_id &&
        result.analysis_id !== completedAnalysisId.value
    ) {
        generatedAiPhotos.value = [];
        aiPhotoGenerationSummary.value = {
            total: 0,
            queued: 0,
            processing: 0,
            completed: 0,
            failed: 0,
        };
        stopAiPhotoPolling();
        stopExportPolling();
        exportState.value = null;
        archivePrepared.value = false;
        exportError.value = '';
    }
    form.title = result.title ?? form.title;
    form.description = result.description ?? form.description;
    form.marketplaceSearchQuery =
        result.marketplace_search_query ?? form.marketplaceSearchQuery;
    form.infographicFeatures = normalizeInfographicFeatures(
        result.infographic_features,
    );
    form.categoryMatch = result.category_match ?? null;
    form.selectedCategory = null;
    completedAnalysisId.value = result.analysis_id ?? completedAnalysisId.value;
    questionnaire.value = null;
    questionnaireAnalysisId.value = null;
    hasAnalyzed.value = true;
    maximumAccessibleStep.value = Math.max(maximumAccessibleStep.value, 2);
    currentStep.value = 2;
};

const requestAiPhotoGeneration = () => {
    if (
        props.aiPhotoProcessing ||
        !completedAnalysisId.value ||
        selectedAiPhotoScenarios.value.length === 0
    ) {
        return;
    }

    analysisError.value = '';
    emit('generate-ai-photos', {
        analysisId: completedAnalysisId.value,
        scenarios: selectedAiPhotoScenarios.value,
        infographicFeatures: form.infographicFeatures,
    });
    form.aiPhotoScenarios = [];
    expandedAiPhotoCategoryId.value = null;
};

const saveCard = () => {
    if (!isEditing.value || props.saveProcessing) {
        return;
    }

    editMessage.value = '';
    editError.value = '';
    emit('save-card', {
        analysisId: completedAnalysisId.value,
        title: form.title,
        description: form.description,
    });
};

const requestTextRegeneration = () => {
    if (!isEditing.value || props.textRegenerationProcessing) {
        return;
    }

    editMessage.value = '';
    editError.value = '';
    emit('regenerate-text', { analysisId: completedAnalysisId.value });
};

const markCardSaved = (message = 'Изменения сохранены.') => {
    editError.value = '';
    editMessage.value = message;
};

const showEditError = (message) => {
    editMessage.value = '';
    editError.value = message;
};

const stopAiPhotoPolling = () => {
    if (aiPhotoPollingTimer !== null) {
        window.clearInterval(aiPhotoPollingTimer);
        aiPhotoPollingTimer = null;
    }
};

const refreshAiPhotoGeneration = async () => {
    if (!completedAnalysisId.value) {
        return;
    }

    try {
        const response = await fetch(
            route('card-generations.images.index', completedAnalysisId.value),
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error('Не удалось получить статус генерации фото.');
        }

        const result = await response.json();
        generatedAiPhotos.value = Array.isArray(result.images)
            ? result.images
            : [];
        aiPhotoGenerationSummary.value = {
            total: Number(result.summary?.total ?? 0),
            queued: Number(result.summary?.queued ?? 0),
            processing: Number(result.summary?.processing ?? 0),
            completed: Number(result.summary?.completed ?? 0),
            failed: Number(result.summary?.failed ?? 0),
        };

        if (!hasPendingAiPhotos.value) {
            stopAiPhotoPolling();
            emit('ai-photo-settled');
        }
    } catch (error) {
        stopAiPhotoPolling();
        analysisError.value =
            error instanceof Error
                ? error.message
                : 'Не удалось получить статус генерации фото.';
    }
};

const queueAiPhotoGeneration = () => {
    void refreshAiPhotoGeneration();
    stopAiPhotoPolling();
    aiPhotoPollingTimer = window.setInterval(() => {
        void refreshAiPhotoGeneration();
    }, 3000);
};

const selectCompetitorCards = (useCompetitorCards) => {
    form.useCompetitorCards = useCompetitorCards;

    if (!useCompetitorCards || props.competitorSearchProcessing) {
        return;
    }

    emit('search-competitors', {
        marketplaceSearchQuery: form.marketplaceSearchQuery,
        marketplace: form.marketplace,
    });
};

const showAnalysisError = (errors) => {
    const firstError = Object.values(errors).find(
        (error) => typeof error === 'string',
    );

    analysisError.value =
        errors.copywriting_quality ??
        errors.analysis ??
        errors.answers ??
        errors.photos ??
        firstError ??
        'Не удалось обработать фотографии. Попробуйте ещё раз.';
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
                'Не удалось выполнить запрос.',
        );
    }

    return result;
};

const stopExportPolling = () => {
    if (exportPollingTimer !== null) {
        window.clearInterval(exportPollingTimer);
        exportPollingTimer = null;
    }
};

const refreshExportStatus = async () => {
    if (!exportState.value?.export_id) {
        return;
    }

    try {
        const response = await fetch(
            route('card-exports.show', exportState.value.export_id),
            { headers: { Accept: 'application/json' } },
        );
        const result = await responseJson(response);
        exportState.value = { ...exportState.value, ...result };

        if (['completed', 'failed'].includes(result.status)) {
            stopExportPolling();
        }
    } catch (error) {
        stopExportPolling();
        showExportError(
            error instanceof Error
                ? error.message
                : 'Не удалось проверить статус экспорта.',
        );
    }
};

const beginPublication = (result) => {
    exportError.value = '';
    exportState.value = result;
    stopExportPolling();

    if (!['completed', 'failed'].includes(result.status)) {
        exportPollingTimer = window.setInterval(() => {
            void refreshExportStatus();
        }, 5000);
    }
};

const markArchivePrepared = () => {
    exportError.value = '';
    archivePrepared.value = true;
};

const showExportError = (error) => {
    exportError.value =
        typeof error === 'string'
            ? error
            : 'Не удалось экспортировать карточку.';
};

const requestArchive = () => {
    if (!completedAnalysisId.value) {
        showExportError('Сначала завершите анализ карточки.');

        return;
    }

    exportError.value = '';
    emit('download-archive', { analysisId: completedAnalysisId.value });
};

const requestPublication = () => {
    if (!completedAnalysisId.value || !form.selectedCategory) {
        showExportError('Сначала выберите категорию товара.');

        return;
    }

    exportError.value = '';
    emit('publish', {
        analysisId: completedAnalysisId.value,
        publication: {
            title: form.title,
            description: form.description,
            category_id: form.selectedCategory.category_id,
            type_id: form.selectedCategory.type_id,
        },
    });
};

defineExpose({
    beginPublication,
    completeAnalysis,
    markArchivePrepared,
    queueAiPhotoGeneration,
    markCardSaved,
    showAnalysisError,
    showEditError,
    showExportError,
    showQuestionnaire,
});

watch(
    () => form.marketplace,
    (marketplace) => {
        if (typeof window === 'undefined') {
            return;
        }

        if (marketplace) {
            localStorage.setItem(marketplaceStorageKey, marketplace);
        } else {
            localStorage.removeItem(marketplaceStorageKey);
        }

        void refreshMarketplaceApiKeyStatus(marketplace);
    },
    { immediate: true },
);

watch(
    () => props.card,
    (card) => {
        form.id = card.id;
        form.title = card.title ?? '';
        form.description = card.description ?? '';
        form.marketplaceSearchQuery = card.marketplace_search_query ?? '';
        form.marketplace = normalizeMarketplace(card.marketplace);
        form.generationMode =
            card.generation_mode === 'quick' ? 'quick' : 'standard';
        form.copywritingQuality =
            card.copywriting_quality === 'pro' ? 'pro' : 'standard';
        form.useCompetitorCards =
            typeof card.use_competitor_cards === 'boolean'
                ? card.use_competitor_cards
                : null;
        form.categoryMatch = card.category_match ?? null;
        form.selectedCategory = card.selected_category ?? null;
        form.attributes = { ...(card.attributes ?? {}) };
        form.images = normalizeImages(card.images);
        form.infographicFeatures = normalizeInfographicFeatures(
            card.infographic_features,
        );
        generatedAiPhotos.value = Array.isArray(card.generated_images)
            ? card.generated_images
            : [];
        aiPhotoGenerationSummary.value = {
            total: Number(card.image_generation_summary?.total ?? 0),
            queued: Number(card.image_generation_summary?.queued ?? 0),
            processing: Number(card.image_generation_summary?.processing ?? 0),
            completed: Number(card.image_generation_summary?.completed ?? 0),
            failed: Number(card.image_generation_summary?.failed ?? 0),
        };
        completedAnalysisId.value = card.generation_id ?? null;
        archivePrepared.value = card.archive_prepared === true;
        editMessage.value = '';
        editError.value = '';

        if (card.is_editing === true && completedAnalysisId.value) {
            hasAnalyzed.value = true;
            maximumAccessibleStep.value = steps.length;
            currentStep.value = 3;

            if (hasPendingAiPhotos.value) {
                queueAiPhotoGeneration();
            }
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (form.marketplace) {
        return;
    }

    form.marketplace = normalizeMarketplace(
        localStorage.getItem(marketplaceStorageKey),
    );
});

onBeforeUnmount(() => {
    stopAiPhotoPolling();
    stopExportPolling();
    localImageUrls.forEach((url) => URL.revokeObjectURL(url));
});
</script>

<template>
    <section
        class="mx-auto w-full max-w-[1500px] px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-6"
    >
        <Teleport to="body">
            <div
                v-if="processing || textRegenerationProcessing"
                class="fixed inset-0 z-[100] flex min-h-[100dvh] items-center justify-center bg-white/95 p-6 backdrop-blur-sm dark:bg-slate-950/95"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div
                    class="flex max-w-sm flex-col items-center gap-4 text-center"
                >
                    <span
                        class="h-12 w-12 animate-spin rounded-full border-4 border-violet-100 border-t-violet-600 dark:border-slate-700 dark:border-t-violet-400"
                    />
                    <div>
                        <p class="font-bold text-slate-900 dark:text-white">
                            {{
                                textRegenerationProcessing
                                    ? 'Перегенерируем текст карточки'
                                    : questionnaire
                                      ? 'Создаём текст карточки'
                                      : form.generationMode === 'standard'
                                        ? 'Готовим персональный опрос'
                                        : 'Создаём текст карточки'
                            }}
                        </p>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            Анализируем фотографии. Это может занять немного
                            времени.
                        </p>
                    </div>
                </div>
            </div>
        </Teleport>

        <div class="mb-6">
            <div
                v-if="isEditing"
                class="flex flex-col gap-3 rounded-2xl border border-violet-200 bg-violet-50/70 p-4 dark:border-violet-500/30 dark:bg-violet-500/10 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-sm font-bold text-violet-950 dark:text-violet-100"
                    >
                        Редактирование сохранённой карточки
                    </p>
                    <p
                        class="mt-1 text-xs text-violet-700 dark:text-violet-300"
                    >
                        Исходники и маркетплейс сохранены. Текст, характеристики
                        и новые фото можно изменять.
                    </p>
                </div>
                <Link
                    href="/history"
                    class="shrink-0 text-sm font-bold text-violet-700 hover:text-violet-900 dark:text-violet-300 dark:hover:text-white"
                >
                    ← Мои карточки
                </Link>
            </div>
        </div>

        <StepNavigation
            :current-step="currentStep"
            :maximum-accessible-step="maximumAccessibleStep"
            :minimum-accessible-step="maximumAccessibleStep >= 2 ? 2 : 1"
            :steps="steps"
            @select="goToStep"
        />

        <div class="mx-auto max-w-4xl">
            <div
                class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6"
                >
                    <h2
                        class="text-lg font-bold text-slate-950 dark:text-white"
                    >
                        {{ steps[currentStep - 1].title }}
                    </h2>
                </div>

                <div class="p-5 sm:p-6">
                    <p
                        v-if="analysisError"
                        class="mb-5 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-300"
                        role="alert"
                    >
                        {{ analysisError }}
                    </p>
                    <input
                        ref="fileInput"
                        type="file"
                        :accept="
                            form.marketplace === 'ozon'
                                ? 'image/png,image/jpeg'
                                : 'image/png,image/jpeg,image/webp'
                        "
                        multiple
                        class="hidden"
                        @change="addImages"
                    />

                    <QuestionnaireDialog
                        v-if="currentStep === 1 && questionnaire"
                        :questionnaire="questionnaire"
                        :answers="questionnaireAnswers"
                        :processing="processing"
                        @submit="submitQuestionnaire"
                        @update:answers="questionnaireAnswers = $event"
                    />
                    <div
                        v-else-if="currentStep === 1"
                        class="flex flex-col gap-6"
                    >
                        <div class="flex flex-col gap-4">
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                                    >
                                        Загрузите фотографии товара.
                                    </h3>
                                </div>
                                <span
                                    class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ totalImageSizeInMegabytes }} /
                                    {{ maximumTotalImageSizeInMegabytes }} МБ
                                </span>
                            </div>

                            <button
                                type="button"
                                class="rounded-2xl border-2 border-dashed border-slate-300 px-6 py-8 text-center transition hover:border-violet-500 hover:bg-violet-50/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:hover:bg-violet-500/5 lg:py-20"
                                :disabled="
                                    photoCount >= maximumPhotoCount ||
                                    totalImageSizeInBytes >=
                                        maximumTotalImageSizeInBytes
                                "
                                @click="openFilePicker"
                            >
                                <span
                                    class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            d="M12 16V4M7 9l5-5 5 5M4 15v5h16v-5"
                                        />
                                    </svg>
                                </span>
                                <span
                                    class="mt-3 block text-sm font-bold text-slate-800 dark:text-slate-200"
                                >
                                    Выбрать фотографии
                                </span>
                                <span
                                    class="mt-1 block text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        form.marketplace === 'ozon'
                                            ? 'PNG или JPG'
                                            : 'PNG, JPG или WEBP'
                                    }}
                                    · общий размер до
                                    {{ maximumTotalImageSizeInMegabytes }} МБ
                                </span>
                            </button>

                            <p
                                class="text-center text-xs text-slate-500 dark:text-slate-400"
                            >
                                Для качественного результата достаточно 1–2
                                фото. Общий размер — до
                                {{ maximumTotalImageSizeInMegabytes }} МБ. Можно
                                загрузить до 5 фото.
                            </p>
                            <p
                                v-if="uploadError"
                                class="rounded-lg bg-red-50 px-3 py-2 text-center text-xs font-medium text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                role="alert"
                            >
                                {{ uploadError }}
                            </p>

                            <div
                                v-if="form.images.length"
                                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                            >
                                <div
                                    v-for="(image, index) in form.images"
                                    :key="image.id"
                                    class="group relative aspect-square overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-800"
                                >
                                    <img
                                        :src="image.url"
                                        :alt="image.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <span
                                        v-if="index === 0"
                                        class="absolute left-2 top-2 rounded-md bg-slate-950/75 px-2 py-1 text-[10px] font-bold text-white"
                                    >
                                        Главное
                                    </span>
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-lg bg-white/90 text-slate-600 opacity-0 shadow-sm transition hover:text-red-600 focus:opacity-100 group-hover:opacity-100"
                                        :aria-label="`Удалить ${image.name}`"
                                        @click="removeImage(index)"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path d="M6 6l12 12M18 6 6 18" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-200 dark:border-slate-800"
                        />

                        <div class="flex flex-col gap-3">
                            <p
                                class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                            >
                                Для какого маркетплейса создаём карточку?
                            </p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    class="group relative flex min-h-24 items-center justify-between overflow-hidden rounded-2xl border-2 p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                                    :class="
                                        form.marketplace === 'wildberries'
                                            ? 'border-fuchsia-500 bg-fuchsia-50 shadow-sm shadow-fuchsia-500/10 dark:bg-fuchsia-500/10'
                                            : 'border-slate-200 bg-white hover:border-fuchsia-300 dark:border-slate-700 dark:bg-slate-950 dark:hover:border-fuchsia-600'
                                    "
                                    @click="form.marketplace = 'wildberries'"
                                >
                                    <span>
                                        <span
                                            class="block text-xs font-bold uppercase tracking-wider text-fuchsia-600 dark:text-fuchsia-400"
                                            >WB</span
                                        >
                                        <span
                                            class="mt-1 block text-base font-bold text-slate-900 dark:text-white"
                                            >Wildberries</span
                                        >
                                    </span>
                                    <span
                                        v-if="
                                            form.marketplace === 'wildberries'
                                        "
                                        class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-fuchsia-600 text-white"
                                    >
                                        <svg
                                            class="h-3 w-3"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="3"
                                        >
                                            <path d="m5 12 4 4L19 6" />
                                        </svg>
                                    </span>
                                </button>

                                <button
                                    type="button"
                                    class="group relative flex min-h-24 items-center justify-between overflow-hidden rounded-2xl border-2 p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                                    :class="
                                        form.marketplace === 'ozon'
                                            ? 'border-blue-500 bg-blue-50 shadow-sm shadow-blue-500/10 dark:bg-blue-500/10'
                                            : 'border-slate-200 bg-white hover:border-blue-300 dark:border-slate-700 dark:bg-slate-950 dark:hover:border-blue-600'
                                    "
                                    @click="form.marketplace = 'ozon'"
                                >
                                    <span>
                                        <span
                                            class="block text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400"
                                            >Ozon</span
                                        >
                                        <span
                                            class="mt-1 block text-base font-bold text-slate-900 dark:text-white"
                                            >Ozon</span
                                        >
                                    </span>
                                    <span
                                        v-if="form.marketplace === 'ozon'"
                                        class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-white"
                                    >
                                        <svg
                                            class="h-3 w-3"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="3"
                                        >
                                            <path d="m5 12 4 4L19 6" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                            <p
                                class="text-center text-xs text-slate-500 dark:text-slate-400"
                            >
                                Позже можно будет адаптировать под другой
                                маркетплейс.
                            </p>
                        </div>

                        <fieldset
                            class="border-t border-slate-200 pt-5 dark:border-slate-800"
                        >
                            <legend
                                class="px-1 text-sm font-semibold text-slate-800 dark:text-slate-200"
                            >
                                Режим генерации
                            </legend>
                            <div
                                class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1.5 dark:bg-slate-950"
                            >
                                <button
                                    type="button"
                                    class="flex min-w-0 items-center justify-center gap-2 rounded-lg px-2 py-2.5 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                                    :class="
                                        form.generationMode === 'quick'
                                            ? 'bg-white text-violet-700 shadow-sm dark:bg-slate-800 dark:text-violet-300'
                                            : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'
                                    "
                                    @click="form.generationMode = 'quick'"
                                >
                                    <span
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md"
                                        :class="
                                            form.generationMode === 'quick'
                                                ? 'bg-violet-100 dark:bg-violet-500/15'
                                                : 'bg-slate-200 dark:bg-slate-800'
                                        "
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                d="m13 2-9 12h7l-1 8 9-12h-7l1-8Z"
                                            />
                                        </svg>
                                    </span>
                                    <span class="min-w-0">
                                        <span
                                            class="block truncate text-xs font-bold sm:text-sm"
                                            >Быстрый</span
                                        >
                                        <span
                                            class="hidden truncate text-[10px] text-slate-400 min-[360px]:block"
                                            >Быстрее результат</span
                                        >
                                    </span>
                                </button>

                                <button
                                    type="button"
                                    class="flex min-w-0 items-center justify-center gap-2 rounded-lg px-2 py-2.5 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                                    :class="
                                        form.generationMode === 'standard'
                                            ? 'bg-white text-violet-700 shadow-sm dark:bg-slate-800 dark:text-violet-300'
                                            : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'
                                    "
                                    @click="form.generationMode = 'standard'"
                                >
                                    <span
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md"
                                        :class="
                                            form.generationMode === 'standard'
                                                ? 'bg-violet-100 dark:bg-violet-500/15'
                                                : 'bg-slate-200 dark:bg-slate-800'
                                        "
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                d="M12 3 9.8 8.8 4 11l5.8 2.2L12 19l2.2-5.8L20 11l-5.8-2.2L12 3Z"
                                            />
                                        </svg>
                                    </span>
                                    <span class="min-w-0">
                                        <span
                                            class="block truncate text-xs font-bold sm:text-sm"
                                            >Обычный</span
                                        >
                                        <span
                                            class="hidden truncate text-[10px] text-slate-400 min-[360px]:block"
                                            >Больше деталей</span
                                        >
                                    </span>
                                </button>
                            </div>
                        </fieldset>

                        <fieldset
                            class="border-t border-slate-200 pt-5 dark:border-slate-800"
                        >
                            <legend
                                class="px-1 text-sm font-semibold text-slate-800 dark:text-slate-200"
                            >
                                Качество копирайтинга
                            </legend>
                            <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                <button
                                    type="button"
                                    class="relative flex min-w-0 flex-col items-center rounded-xl border-2 px-2 py-3 text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 sm:px-4"
                                    :class="
                                        form.copywritingQuality === 'standard'
                                            ? 'border-violet-500 bg-violet-50 shadow-sm shadow-violet-500/10 dark:bg-violet-500/10'
                                            : 'border-slate-200 bg-white hover:border-violet-300 dark:border-slate-700 dark:bg-slate-950 dark:hover:border-violet-600'
                                    "
                                    :aria-pressed="
                                        form.copywritingQuality === 'standard'
                                    "
                                    @click="
                                        form.copywritingQuality = 'standard'
                                    "
                                >
                                    <span
                                        class="truncate text-xs font-bold text-slate-900 dark:text-white sm:text-sm"
                                    >
                                        Хорошее
                                    </span>
                                    <span
                                        class="mt-0.5 truncate text-[10px] text-slate-500 dark:text-slate-400 sm:text-xs"
                                    >
                                        Базовый текст
                                    </span>
                                    <span
                                        class="mt-2 rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        Без доплаты
                                    </span>
                                </button>

                                <button
                                    type="button"
                                    class="relative flex min-w-0 flex-col items-center rounded-xl border-2 px-2 py-3 text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 sm:px-4"
                                    :class="
                                        form.copywritingQuality === 'pro'
                                            ? 'border-violet-600 bg-violet-50 shadow-sm shadow-violet-500/15 dark:bg-violet-500/10'
                                            : 'border-slate-200 bg-white hover:border-violet-300 dark:border-slate-700 dark:bg-slate-950 dark:hover:border-violet-600'
                                    "
                                    :aria-pressed="
                                        form.copywritingQuality === 'pro'
                                    "
                                    @click="form.copywritingQuality = 'pro'"
                                >
                                    <span
                                        class="truncate text-xs font-black text-violet-700 dark:text-violet-300 sm:text-sm"
                                    >
                                        PRO-копирайтинг
                                    </span>
                                    <span
                                        class="mt-0.5 truncate text-[10px] text-slate-500 dark:text-slate-400 sm:text-xs"
                                    >
                                        Усиленный текст
                                    </span>
                                    <span
                                        class="mt-2 inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-1 text-[10px] font-black text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                                    >
                                        <img
                                            src="/assets/brand/zarqi-lightning-icon.svg"
                                            alt=""
                                            class="h-3.5 w-3.5"
                                        />
                                        {{ pricing.cost_pro_copywriting ?? 50 }}
                                    </span>
                                </button>
                            </div>
                        </fieldset>

                        <div class="flex justify-center pt-1">
                            <button
                                type="button"
                                class="inline-flex min-w-48 items-center justify-center gap-2 rounded-xl bg-violet-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-violet-600/20 transition hover:bg-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-45 disabled:shadow-none"
                                :disabled="
                                    processing ||
                                    photoCount === 0 ||
                                    !form.marketplace
                                "
                                :title="
                                    photoCount === 0
                                        ? 'Сначала загрузите фотографию'
                                        : !form.marketplace
                                          ? 'Сначала выберите маркетплейс'
                                          : 'Анализировать фотографии'
                                "
                                @click="requestAnalysis"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        d="M12 3 9.8 8.8 4 11l5.8 2.2L12 19l2.2-5.8L20 11l-5.8-2.2L12 3Z"
                                    />
                                    <path
                                        d="m19 3 .7 1.8L22 6l-2.3 1.2L19 9l-.7-1.8L16 6l2.3-1.2L19 3Z"
                                    />
                                </svg>
                                Анализировать
                            </button>
                        </div>
                    </div>

                    <div
                        v-else-if="currentStep === 3"
                        class="flex flex-col gap-5"
                    >
                        <SeoTextStep
                            :form="form"
                            :description-word-count="descriptionWordCount"
                            :competitor-search-processing="
                                competitorSearchProcessing
                            "
                            :has-marketplace-api-key="hasMarketplaceApiKey"
                            :marketplace="form.marketplace"
                            :marketplace-name="marketplaceName"
                            @api-key-saved="markMarketplaceApiKeySaved"
                            @select-competitor-cards="selectCompetitorCards"
                            @update-description="form.description = $event"
                            @update-title="form.title = $event"
                        />
                        <div
                            v-if="isEditing"
                            class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950"
                        >
                            <p
                                v-if="editError"
                                class="text-sm font-semibold text-red-600 dark:text-red-400"
                                role="alert"
                            >
                                {{ editError }}
                            </p>
                            <p
                                v-if="editMessage"
                                class="text-sm font-semibold text-emerald-600 dark:text-emerald-400"
                                role="status"
                            >
                                {{ editMessage }}
                            </p>
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <button
                                    type="button"
                                    class="flex flex-1 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                    :disabled="saveProcessing"
                                    @click="saveCard"
                                >
                                    {{
                                        saveProcessing
                                            ? 'Сохраняем…'
                                            : 'Сохранить изменения'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="flex flex-1 items-center justify-center rounded-xl bg-violet-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="textRegenerationProcessing"
                                    @click="requestTextRegeneration"
                                >
                                    Перегенерировать текст ·
                                    {{ pricing.cost_text_regeneration ?? 25 }}
                                    ZARQ
                                </button>
                            </div>
                        </div>
                    </div>
                    <AttributesStep
                        v-else-if="currentStep === 4"
                        v-model="form.selectedCategory"
                        :generation-id="completedAnalysisId"
                        :initial-attributes="form.attributes"
                        :marketplace="form.marketplace"
                        :category-match="form.categoryMatch"
                        @update:attributes="form.attributes = $event"
                    />
                    <AiPhotosStep
                        v-else-if="currentStep === 2"
                        :categories="aiPhotoCategories"
                        :selected-scenarios="form.aiPhotoScenarios"
                        :expanded-category-id="expandedAiPhotoCategoryId"
                        :infographic-features="form.infographicFeatures"
                        :generation-summary="aiPhotoGenerationSummary"
                        :generated-photos="generatedAiPhotos"
                        :processing="aiPhotoProcessing"
                        :image-cost="
                            Number(pricing.cost_image_generation ?? 50)
                        "
                        :selected-count="selectedAiPhotoScenarios.length"
                        :can-generate="
                            Boolean(
                                completedAnalysisId &&
                                form.aiPhotoScenarios.length,
                            )
                        "
                        @select-category="selectAiPhotoCategory"
                        @toggle-scenario="selectAiPhotoScenario"
                        @generate="requestAiPhotoGeneration"
                        @update:infographic-features="
                            form.infographicFeatures = $event
                        "
                    />
                    <ExportStep
                        v-else
                        :marketplace-name="marketplaceName"
                        :export-cost="effectiveExportCost"
                        :image-cost="
                            Number(pricing.cost_image_generation ?? 50)
                        "
                        :has-generated-photos="completedGeneratedPhotoCount > 0"
                        :has-existing-archive="archivePrepared"
                        :has-marketplace-api-key="hasMarketplaceApiKey"
                        :has-pending-ai-photos="hasPendingAiPhotos"
                        :category-selected="Boolean(form.selectedCategory)"
                        :archive-processing="archiveProcessing"
                        :publication-processing="publicationProcessing"
                        :export-state="exportState"
                        :error="exportError"
                        @download-archive="requestArchive"
                        @publish="requestPublication"
                    />
                </div>

                <div
                    class="flex items-center justify-between gap-3 border-t border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6"
                >
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        :disabled="currentStep <= 2"
                        @click="goBack"
                    >
                        Назад
                    </button>
                    <button
                        v-if="currentStep < steps.length"
                        type="button"
                        class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:bg-violet-100 disabled:text-violet-300 dark:disabled:bg-slate-800 dark:disabled:text-slate-500"
                        :disabled="!canGoNext"
                        @click="goNext"
                    >
                        Далее
                    </button>
                    <span
                        v-else
                        class="text-sm font-semibold text-emerald-600 dark:text-emerald-400"
                        >Все шаги заполнены</span
                    >
                </div>
            </div>
        </div>
    </section>
</template>
