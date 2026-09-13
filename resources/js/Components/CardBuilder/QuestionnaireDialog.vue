<script setup>
import { computed } from 'vue';

const props = defineProps({
    questionnaire: { type: Object, required: true },
    answers: { type: Object, required: true },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'update:answers']);

const isComplete = computed(() =>
    props.questionnaire.questions.every((question) => {
        const answer = props.answers[question.id];

        return Array.isArray(answer) ? answer.length > 0 : Boolean(answer);
    }),
);

const setSingleChoice = (questionId, optionId) => {
    emit('update:answers', { ...props.answers, [questionId]: optionId });
};

const toggleMultipleChoice = (questionId, optionId) => {
    const selected = props.answers[questionId] ?? [];
    const answer = selected.includes(optionId)
        ? selected.filter((id) => id !== optionId)
        : [...selected, optionId];

    emit('update:answers', { ...props.answers, [questionId]: answer });
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <div>
            <p
                class="text-xs font-bold uppercase tracking-wider text-violet-600 dark:text-violet-400"
            >
                Персонализация текста
            </p>
            <h3 class="mt-1 text-lg font-bold text-slate-950 dark:text-white">
                Расставьте нужные акценты
            </h3>
            <p
                class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
            >
                Выберите подходящие варианты — нейросеть учтёт их в названии и
                описании товара.
            </p>
        </div>

        <fieldset
            v-for="(question, questionIndex) in questionnaire.questions"
            :key="question.id"
            class="flex flex-col gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
        >
            <legend
                class="px-1 text-sm font-bold text-slate-900 dark:text-white"
            >
                {{ questionIndex + 1 }}. {{ question.question }}
            </legend>
            <div class="flex flex-wrap gap-2">
                <span
                    class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide"
                    :class="
                        question.purpose === 'product_detail'
                            ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300'
                            : 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'
                    "
                    >{{
                        question.purpose === 'product_detail'
                            ? 'О товаре'
                            : 'Стиль подачи'
                    }}</span
                >
                <span
                    v-if="question.type === 'multiple_choice'"
                    class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                    >Можно выбрать несколько</span
                >
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                <label
                    v-for="option in question.options"
                    :key="option.id"
                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm transition hover:border-violet-300 hover:bg-violet-50/60 dark:border-slate-700 dark:hover:border-violet-700 dark:hover:bg-violet-500/10"
                >
                    <input
                        v-if="question.type === 'single_choice'"
                        :checked="answers[question.id] === option.id"
                        :name="question.id"
                        :value="option.id"
                        type="radio"
                        class="mt-0.5 border-slate-300 text-violet-600 focus:ring-violet-500"
                        @change="setSingleChoice(question.id, option.id)"
                    />
                    <input
                        v-else
                        type="checkbox"
                        :checked="
                            (answers[question.id] ?? []).includes(option.id)
                        "
                        class="mt-0.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                        @change="toggleMultipleChoice(question.id, option.id)"
                    />
                    <span
                        class="leading-5 text-slate-700 dark:text-slate-300"
                        >{{ option.label }}</span
                    >
                </label>
            </div>
        </fieldset>

        <button
            type="button"
            class="inline-flex w-full items-center justify-center rounded-xl bg-violet-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-violet-600/20 transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-45"
            :disabled="!isComplete || processing"
            @click="emit('submit')"
        >
            Создать название и описание
        </button>
    </div>
</template>
