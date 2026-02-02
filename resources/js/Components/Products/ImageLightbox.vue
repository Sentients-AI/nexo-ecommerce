<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';

interface Props {
    images: string[];
    initialIndex?: number;
    show: boolean;
    alt?: string;
}

const props = withDefaults(defineProps<Props>(), {
    initialIndex: 0,
    alt: 'Product image',
});

const emit = defineEmits<{
    close: [];
}>();

const currentIndex = ref(props.initialIndex);
const isZoomed = ref(false);
const zoomPosition = ref({ x: 50, y: 50 });

const currentImage = computed(() => props.images[currentIndex.value] ?? '');
const hasMultipleImages = computed(() => props.images.length > 1);

function close() {
    isZoomed.value = false;
    emit('close');
}

function next() {
    if (currentIndex.value < props.images.length - 1) {
        currentIndex.value++;
    } else {
        currentIndex.value = 0;
    }
    isZoomed.value = false;
}

function prev() {
    if (currentIndex.value > 0) {
        currentIndex.value--;
    } else {
        currentIndex.value = props.images.length - 1;
    }
    isZoomed.value = false;
}

function goToImage(index: number) {
    currentIndex.value = index;
    isZoomed.value = false;
}

function toggleZoom() {
    isZoomed.value = !isZoomed.value;
}

function handleMouseMove(e: MouseEvent) {
    if (!isZoomed.value) return;
    const rect = (e.target as HTMLElement).getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    zoomPosition.value = { x, y };
}

function handleKeydown(e: KeyboardEvent) {
    if (!props.show) return;

    switch (e.key) {
        case 'Escape':
            close();
            break;
        case 'ArrowLeft':
            prev();
            break;
        case 'ArrowRight':
            next();
            break;
    }
}

watch(() => props.show, (show) => {
    if (show) {
        currentIndex.value = props.initialIndex;
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
        isZoomed.value = false;
    }
});

watch(() => props.initialIndex, (index) => {
    currentIndex.value = index;
});

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-show="show"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
                @click.self="close"
            >
                <!-- Close button -->
                <button
                    @click="close"
                    class="absolute top-4 right-4 z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition-colors"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Image counter -->
                <div
                    v-if="hasMultipleImages"
                    class="absolute top-4 left-4 z-10 rounded-full bg-white/10 px-3 py-1 text-sm text-white"
                >
                    {{ currentIndex + 1 }} / {{ images.length }}
                </div>

                <!-- Previous button -->
                <button
                    v-if="hasMultipleImages"
                    @click="prev"
                    class="absolute left-4 z-10 rounded-full bg-white/10 p-3 text-white hover:bg-white/20 transition-colors"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <!-- Main image -->
                <div
                    class="relative max-h-[80vh] max-w-[90vw] overflow-hidden"
                    :class="isZoomed ? 'cursor-zoom-out' : 'cursor-zoom-in'"
                    @click="toggleZoom"
                    @mousemove="handleMouseMove"
                >
                    <Transition
                        enter-active-class="duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="duration-150 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                        mode="out-in"
                    >
                        <img
                            :key="currentIndex"
                            :src="currentImage"
                            :alt="`${alt} ${currentIndex + 1}`"
                            class="max-h-[80vh] max-w-[90vw] object-contain transition-transform duration-200"
                            :style="isZoomed ? {
                                transform: 'scale(2)',
                                transformOrigin: `${zoomPosition.x}% ${zoomPosition.y}%`
                            } : {}"
                        />
                    </Transition>
                </div>

                <!-- Next button -->
                <button
                    v-if="hasMultipleImages"
                    @click="next"
                    class="absolute right-4 z-10 rounded-full bg-white/10 p-3 text-white hover:bg-white/20 transition-colors"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>

                <!-- Thumbnails -->
                <div
                    v-if="hasMultipleImages"
                    class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 rounded-lg bg-white/10 p-2"
                >
                    <button
                        v-for="(image, index) in images"
                        :key="index"
                        @click.stop="goToImage(index)"
                        class="h-12 w-12 overflow-hidden rounded-md transition-all"
                        :class="index === currentIndex ? 'ring-2 ring-white' : 'opacity-60 hover:opacity-100'"
                    >
                        <img
                            :src="image"
                            :alt="`Thumbnail ${index + 1}`"
                            class="h-full w-full object-cover"
                        />
                    </button>
                </div>

                <!-- Zoom hint -->
                <div
                    v-if="!isZoomed"
                    class="absolute bottom-4 right-4 flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm text-white"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
                    </svg>
                    Click to zoom
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
