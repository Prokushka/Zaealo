<script setup>
defineProps({
    currentStep: { type: Number, required: true },
    maximumAccessibleStep: { type: Number, required: true },
    minimumAccessibleStep: { type: Number, default: 1 },
    steps: { type: Array, required: true },
});

const emit = defineEmits(['select']);
</script>

<template>
    <div class="mb-6 hidden overflow-x-auto pb-2 lg:block">
        <ol class="flex min-w-[620px] items-start">
            <li
                v-for="(step, index) in steps"
                :key="step.number"
                class="flex flex-1 items-start"
            >
                <button
                    type="button"
                    class="group flex min-w-0 flex-col items-center gap-2 text-center disabled:cursor-not-allowed"
                    :disabled="
                        step.number > maximumAccessibleStep ||
                        step.number < minimumAccessibleStep
                    "
                    @click="emit('select', step.number)"
                >
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-full border-2 text-sm font-bold transition"
                        :class="
                            currentStep === step.number
                                ? 'border-violet-600 bg-violet-600 text-white shadow-md shadow-violet-600/20'
                                : currentStep > step.number
                                  ? 'border-violet-600 bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'
                                  : step.number <= maximumAccessibleStep
                                    ? 'border-violet-300 bg-violet-50 text-violet-600 group-hover:border-violet-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                    : 'border-slate-200 bg-slate-100 text-slate-300 opacity-70 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-600'
                        "
                    >
                        <svg
                            v-if="step.number > maximumAccessibleStep"
                            class="h-3.5 w-3.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <rect x="5" y="10" width="14" height="10" rx="2" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        </svg>
                        <svg
                            v-else-if="currentStep > step.number"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path d="m5 12 4 4L19 6" />
                        </svg>
                        <span v-else>{{ step.number }}</span>
                    </span>
                    <span
                        class="text-xs font-semibold"
                        :class="
                            currentStep === step.number
                                ? 'text-violet-700 dark:text-violet-300'
                                : step.number <= maximumAccessibleStep
                                  ? 'text-violet-600 dark:text-slate-300'
                                  : 'text-slate-300 dark:text-slate-600'
                        "
                        >{{ step.title }}</span
                    >
                </button>
                <div
                    v-if="index < steps.length - 1"
                    class="mt-[17px] h-0.5 flex-1"
                    :class="
                        maximumAccessibleStep > step.number
                            ? 'bg-violet-600'
                            : 'bg-slate-200 dark:bg-slate-800'
                    "
                />
            </li>
        </ol>
    </div>

    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 pt-2 shadow-[0_-8px_24px_rgba(15,23,42,0.08)] backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/95 lg:hidden"
        aria-label="Шаги создания карточки"
    >
        <div
            class="mx-auto grid max-w-3xl grid-cols-5 gap-1 pb-[calc(0.5rem+env(safe-area-inset-bottom))]"
        >
            <button
                v-for="step in steps"
                :key="`mobile-step-${step.number}`"
                type="button"
                class="group flex min-w-0 flex-col items-center gap-1 rounded-xl px-1 py-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 disabled:cursor-not-allowed"
                :disabled="
                    step.number > maximumAccessibleStep ||
                    step.number < minimumAccessibleStep
                "
                :class="
                    currentStep === step.number
                        ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'
                        : step.number <= maximumAccessibleStep
                          ? 'bg-violet-50/70 text-violet-600 hover:bg-violet-100 dark:bg-slate-800/80 dark:text-slate-300 dark:hover:bg-slate-800'
                          : 'text-slate-300 opacity-55 dark:text-slate-600'
                "
                :aria-current="currentStep === step.number ? 'step' : undefined"
                @click="emit('select', step.number)"
            >
                <span
                    class="flex h-7 w-7 items-center justify-center rounded-full border text-[11px] font-bold transition"
                    :class="
                        currentStep === step.number
                            ? 'border-violet-600 bg-violet-600 text-white shadow-sm shadow-violet-600/25'
                            : currentStep > step.number
                              ? 'border-violet-300 bg-violet-50 text-violet-600 dark:border-violet-500/40 dark:bg-violet-500/10 dark:text-violet-300'
                              : step.number <= maximumAccessibleStep
                                ? 'border-violet-300 bg-white text-violet-600 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200'
                                : 'border-slate-200 bg-slate-100 text-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-600'
                    "
                >
                    <svg
                        v-if="step.number > maximumAccessibleStep"
                        class="h-3 w-3"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <rect x="5" y="10" width="14" height="10" rx="2" />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                    </svg>
                    <svg
                        v-else-if="currentStep > step.number"
                        class="h-3.5 w-3.5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path d="m5 12 4 4L19 6" />
                    </svg>
                    <span v-else>{{ step.number }}</span>
                </span>
                <span
                    class="block w-full truncate text-[10px] font-semibold leading-tight min-[400px]:text-[11px]"
                    >{{ step.shortTitle }}</span
                >
            </button>
        </div>
    </nav>
</template>
