<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface Props {
    show: boolean;
    type?: ToastType;
    message: string;
    duration?: number;
    position?: 'top-right' | 'top-center' | 'bottom-right' | 'bottom-center';
}

const props = withDefaults(defineProps<Props>(), {
    type: 'info',
    duration: 5000,
    position: 'top-right',
});

const emit = defineEmits<{
    close: [];
}>();

const visible = ref(false);

const typeClasses: Record<ToastType, { bg: string; icon: string; text: string }> = {
    success: {
        bg: 'bg-green-50 dark:bg-green-900/50',
        icon: 'text-green-400',
        text: 'text-green-800 dark:text-green-200',
    },
    error: {
        bg: 'bg-red-50 dark:bg-red-900/50',
        icon: 'text-red-400',
        text: 'text-red-800 dark:text-red-200',
    },
    warning: {
        bg: 'bg-yellow-50 dark:bg-yellow-900/50',
        icon: 'text-yellow-400',
        text: 'text-yellow-800 dark:text-yellow-200',
    },
    info: {
        bg: 'bg-blue-50 dark:bg-blue-900/50',
        icon: 'text-blue-400',
        text: 'text-blue-800 dark:text-blue-200',
    },
};

const positionClasses: Record<string, string> = {
    'top-right': 'top-4 right-4',
    'top-center': 'top-4 left-1/2 -translate-x-1/2',
    'bottom-right': 'bottom-4 right-4',
    'bottom-center': 'bottom-4 left-1/2 -translate-x-1/2',
};

let timeout: ReturnType<typeof setTimeout> | null = null;

watch(() => props.show, (show) => {
    visible.value = show;
    if (show && props.duration > 0) {
        if (timeout) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(() => {
            emit('close');
        }, props.duration);
    }
});

onMounted(() => {
    if (props.show) {
        visible.value = true;
        if (props.duration > 0) {
            timeout = setTimeout(() => {
                emit('close');
            }, props.duration);
        }
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-300 ease-out"
            enter-from-class="opacity-0 translate-x-4"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="duration-200 ease-in"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-4"
        >
            <div
                v-show="visible"
                class="fixed z-50 pointer-events-auto"
                :class="positionClasses[position]"
            >
                <div
                    class="rounded-lg p-4 shadow-lg max-w-sm w-full"
                    :class="typeClasses[type].bg"
                >
                    <div class="flex items-start gap-3">
                        <!-- Icon -->
                        <div class="shrink-0" :class="typeClasses[type].icon">
                            <!-- Success icon -->
                            <svg v-if="type === 'success'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                            <!-- Error icon -->
                            <svg v-else-if="type === 'error'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                            </svg>
                            <!-- Warning icon -->
                            <svg v-else-if="type === 'warning'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            <!-- Info icon -->
                            <svg v-else class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                            </svg>
                        </div>

                        <!-- Message -->
                        <p class="text-sm font-medium flex-1" :class="typeClasses[type].text">
                            {{ message }}
                        </p>

                        <!-- Close button -->
                        <button
                            @click="emit('close')"
                            class="shrink-0 rounded-md hover:bg-black/5 dark:hover:bg-white/10 p-1 transition-colors"
                            :class="typeClasses[type].text"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
