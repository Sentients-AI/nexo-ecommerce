<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, usePage, Deferred } from '@inertiajs/vue3';
import { useStockUpdates, usePriceUpdates } from '@/Composables/useStockUpdates';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductCard from '@/Components/Products/ProductCard.vue';
import ImageLightbox from '@/Components/Products/ImageLightbox.vue';
import QuantityStepper from '@/Components/UI/QuantityStepper.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import StarRating from '@/Components/Reviews/StarRating.vue';
import ReviewForm from '@/Components/Reviews/ReviewForm.vue';
import RatingDistribution from '@/Components/Reviews/RatingDistribution.vue';
import ReviewCard from '@/Components/Reviews/ReviewCard.vue';
import { useCart } from '@/Composables/useCart';
import { useCurrency } from '@/Composables/useCurrency';
import { useWishlist } from '@/Composables/useWishlist';
import { useRecentlyViewed } from '@/Composables/useRecentlyViewed';
import { useLocale } from '@/Composables/useLocale';
import { useApi } from '@/Composables/useApi';
import type { ProductApiResource, ReviewApiResource, ReviewReplyApiResource } from '@/types/api';

interface VariantAttributeValue {
    id: number;
    value: string;
    slug: string;
    metadata?: Record<string, string> | null;
    attribute_type_id?: number;
}

interface VariantAttributeType {
    id: number;
    name: string;
    slug: string;
}

interface AttributeValueWithType extends VariantAttributeValue {
    attributeType: VariantAttributeType;
}

interface VariantStock {
    quantity_available: number;
    quantity_reserved: number;
}

interface ProductVariant {
    id: number;
    sku: string;
    price_cents: number | null;
    sale_price: number | null;
    is_active: boolean;
    sort_order: number;
    attributeValues: AttributeValueWithType[];
    stock: VariantStock | null;
}

interface ReviewStats {
    average_rating: number | null;
    review_count: number;
    distribution?: Record<number, number>;
}

interface SeoMeta {
    title: string;
    description: string | null;
    image: string | null;
    canonical_url: string;
    price: number | null;
    currency: string;
}

interface QuestionAnswer {
    id: number;
    body: string;
    is_vendor_answer: boolean;
    author_name: string;
    created_at: string;
}

interface QuestionItem {
    id: number;
    body: string;
    is_answered: boolean;
    author_name: string;
    created_at: string;
    answers: QuestionAnswer[];
}

interface Props {
    product: ProductApiResource & { active_variants?: ProductVariant[] };
    reviewStats: ReviewStats;
    questionCount: number;
    relatedProducts: ProductApiResource[];
    recommendations?: ProductApiResource[];
    seo: SeoMeta;
}

const props = defineProps<Props>();

const page = usePage();
const { addToCart, loading: cartLoading, error: cartError } = useCart();
const { formatPrice } = useCurrency();
const { isInWishlist, toggleWishlist } = useWishlist();
const { addToRecentlyViewed } = useRecentlyViewed();
const { t, localePath } = useLocale();
const { get: apiGet } = useApi();

// Track product view on mount
onMounted(() => {
    addToRecentlyViewed(props.product.id);
});

const quantity = ref(1);
const selectedImage = ref(0);
const addedToCart = ref(false);
const lightboxOpen = ref(false);
const activeTab = ref<'description' | 'specifications' | 'reviews' | 'questions'>('description');

// Variant state
const variants = computed<ProductVariant[]>(() => (props.product as any).active_variants ?? []);
const hasVariants = computed(() => variants.value.length > 0);

// Group attribute types from all variants
const attributeTypes = computed(() => {
    const types = new Map<number, { id: number; name: string; slug: string; values: Map<number, AttributeValueWithType> }>();
    variants.value.forEach(variant => {
        variant.attributeValues.forEach(av => {
            if (!types.has(av.attributeType.id)) {
                types.set(av.attributeType.id, { ...av.attributeType, values: new Map() });
            }
            types.get(av.attributeType.id)!.values.set(av.id, av);
        });
    });
    return Array.from(types.values()).map(t => ({ ...t, values: Array.from(t.values.values()) }));
});

// Track selected attribute value per type { typeId: valueId }
const selectedAttributes = ref<Record<number, number>>({});

// Find matching variant from current selections
const selectedVariant = computed<ProductVariant | null>(() => {
    if (!hasVariants.value) return null;
    const selectedEntries = Object.entries(selectedAttributes.value).map(([k, v]) => [Number(k), v]);
    if (selectedEntries.length === 0) return null;

    return variants.value.find(variant => {
        return selectedEntries.every(([typeId, valueId]) =>
            variant.attributeValues.some(av => av.attributeType.id === typeId && av.id === valueId)
        );
    }) ?? null;
});

// Check if a given attribute value is available given current other selections
function isValueAvailable(typeId: number, valueId: number): boolean {
    if (!hasVariants.value) return true;
    const otherSelections = Object.entries(selectedAttributes.value)
        .filter(([k]) => Number(k) !== typeId)
        .map(([k, v]) => [Number(k), v]);

    return variants.value.some(variant =>
        variant.is_active &&
        variant.attributeValues.some(av => av.attributeType.id === typeId && av.id === valueId) &&
        otherSelections.every(([otherTypeId, otherValueId]) =>
            variant.attributeValues.some(av => av.attributeType.id === otherTypeId && av.id === otherValueId)
        )
    );
}

function selectAttribute(typeId: number, valueId: number): void {
    selectedAttributes.value = { ...selectedAttributes.value, [typeId]: valueId };
}

// Live stock & price state
const liveStock = ref(props.product.stock ?? null);
const livePriceCents = ref(props.product.price_cents);
const liveSalePrice = ref<number | null>(props.product.sale_price ?? null);

// When a variant is selected, update the live price/stock to reflect it
watch(selectedVariant, (variant) => {
    if (variant) {
        livePriceCents.value = variant.price_cents ?? props.product.price_cents;
        liveSalePrice.value = variant.sale_price ?? null;
        liveStock.value = variant.stock ?? null;
    } else {
        livePriceCents.value = props.product.price_cents;
        liveSalePrice.value = props.product.sale_price ?? null;
        liveStock.value = props.product.stock ?? null;
    }
});

useStockUpdates(props.product.id, (payload) => {
    if (liveStock.value) {
        liveStock.value = {
            ...liveStock.value,
            quantity_available: payload.quantity_available,
            quantity_reserved: payload.quantity_reserved,
        };
    }
});

usePriceUpdates(props.product.id, (payload) => {
    livePriceCents.value = payload.price_cents;
    liveSalePrice.value = payload.sale_price;
});

// Reviews state
const reviews = ref<ReviewApiResource[]>([]);
const reviewsLoading = ref(false);
const reviewsNextPage = ref<string | null>(null);
const reviewsLoaded = ref(false);

const isAuthenticated = computed(() => page.props.auth?.user !== null);
const Layout = computed(() => isAuthenticated.value ? AuthenticatedLayout : GuestLayout);

function normalizeImages(images: string | string[] | null | undefined): string[] {
    if (!images) return [];
    if (Array.isArray(images)) return images;
    if (typeof images === 'string') return [images];
    return [];
}

const productImages = computed(() => normalizeImages(props.product.images));

const effectivePrice = computed(() => {
    return liveSalePrice.value ?? livePriceCents.value;
});

const discountPercentage = computed(() => {
    if (!liveSalePrice.value || liveSalePrice.value >= livePriceCents.value) {
        return null;
    }
    return Math.round(((livePriceCents.value - liveSalePrice.value) / livePriceCents.value) * 100);
});

const stockStatus = computed(() => {
    if (!liveStock.value) {
        return { text: t('products.in_stock'), class: 'text-slate-500 dark:text-slate-400', available: true };
    }
    const available = (liveStock.value.quantity_available ?? 0) - (liveStock.value.quantity_reserved ?? 0);
    if (available <= 0) {
        return { text: t('products.out_of_stock'), class: 'text-red-600 dark:text-red-400', available: false };
    }
    if (available <= 5) {
        return { text: `Only ${available} left in stock`, class: 'text-yellow-600 dark:text-yellow-400', available: true };
    }
    return { text: t('products.in_stock'), class: 'text-green-600 dark:text-green-400', available: true };
});

const maxQuantity = computed(() => {
    if (!liveStock.value) {
        return 10;
    }
    const available = (liveStock.value.quantity_available ?? 0) - (liveStock.value.quantity_reserved ?? 0);
    return Math.max(1, Math.min(available, 10));
});

async function handleAddToCart() {
    addedToCart.value = false;
    const success = await addToCart(props.product.id, quantity.value, selectedVariant.value?.id ?? null);
    if (success) {
        addedToCart.value = true;
        setTimeout(() => {
            addedToCart.value = false;
        }, 3000);
    }
}

function openLightbox(index: number) {
    selectedImage.value = index;
    lightboxOpen.value = true;
}

// Load reviews when reviews tab is activated
async function loadReviews() {
    if (reviewsLoaded.value || reviewsLoading.value) return;

    reviewsLoading.value = true;
    const result = await apiGet<{ data: ReviewApiResource[]; next_page_url: string | null }>(
        `/api/v1/products/${props.product.slug}/reviews`
    );

    if (result) {
        reviews.value = result.data || [];
        reviewsNextPage.value = result.next_page_url || null;
    }
    reviewsLoaded.value = true;
    reviewsLoading.value = false;
}

async function loadMoreReviews() {
    if (!reviewsNextPage.value || reviewsLoading.value) return;

    reviewsLoading.value = true;
    const result = await apiGet<{ data: ReviewApiResource[]; next_page_url: string | null }>(
        reviewsNextPage.value
    );

    if (result) {
        reviews.value.push(...(result.data || []));
        reviewsNextPage.value = result.next_page_url || null;
    }
    reviewsLoading.value = false;
}

function handleReviewSubmitted(review: ReviewApiResource) {
    reviews.value.unshift(review);
}

function handleReplyAdded(reviewId: number, reply: ReviewReplyApiResource): void {
    const review = reviews.value.find(r => r.id === reviewId);
    if (review) {
        if (!review.replies) {
            review.replies = [];
        }
        review.replies.push(reply);
    }
}

function handleVoteUpdated(reviewId: number, helpfulCount: number, notHelpfulCount: number, userVote: boolean | null): void {
    const review = reviews.value.find(r => r.id === reviewId);
    if (review) {
        review.helpful_count = helpfulCount;
        review.not_helpful_count = notHelpfulCount;
        review.user_vote = userVote;
    }
}

const hasAlreadyReviewed = computed(() => {
    const userId = (page.props as Record<string, any>).auth?.user?.id;
    if (!userId) {
        return false;
    }
    return reviews.value.some(r => r.user_id === userId);
});

function switchToReviews() {
    activeTab.value = 'reviews';
    loadReviews();
}

// ── Q&A state ────────────────────────────────────────────────────────────────
const questions = ref<QuestionItem[]>([]);
const questionsLoading = ref(false);
const questionsLoaded = ref(false);
const questionsNextPage = ref<string | null>(null);
const questionBody = ref('');
const questionSubmitting = ref(false);
const answerBody = ref<Record<number, string>>({});
const answerSubmitting = ref<Record<number, boolean>>({});
const expandedAnswers = ref<Record<number, boolean>>({});

async function loadQuestions() {
    if (questionsLoaded.value || questionsLoading.value) return;
    questionsLoading.value = true;
    try {
        const result = await useApi().get(`/api/v1/products/${props.product.slug}/questions`);
        questions.value = result.data ?? [];
        questionsNextPage.value = result.next_page_url || null;
        questionsLoaded.value = true;
    } finally {
        questionsLoading.value = false;
    }
}

function switchToQuestions() {
    activeTab.value = 'questions';
    loadQuestions();
}

async function submitQuestion() {
    if (!questionBody.value.trim() || questionSubmitting.value) return;
    questionSubmitting.value = true;
    try {
        const result = await useApi().post(`/api/v1/products/${props.product.slug}/questions`, { body: questionBody.value });
        questions.value.unshift(result.data);
        questionBody.value = '';
    } finally {
        questionSubmitting.value = false;
    }
}

async function submitAnswer(question: QuestionItem) {
    const body = answerBody.value[question.id];
    if (!body?.trim() || answerSubmitting.value[question.id]) return;
    answerSubmitting.value[question.id] = true;
    try {
        const result = await useApi().post(`/api/v1/questions/${question.id}/answers`, { body });
        question.answers.push(result.data);
        question.is_answered = true;
        answerBody.value[question.id] = '';
        expandedAnswers.value[question.id] = false;
    } finally {
        answerSubmitting.value[question.id] = false;
    }
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

const trustBadges = computed(() => [
    { icon: 'shield', text: t('products.secure_checkout') || 'Secure Checkout' },
    { icon: 'truck', text: t('products.free_shipping') || 'Free Shipping Over $50' },
    { icon: 'refresh', text: t('products.returns_policy') || '30-Day Returns' },
]);

// Social sharing
const linkCopied = ref(false);

const shareLinks = computed(() => ({
    twitter: `https://twitter.com/intent/tweet?text=${encodeURIComponent(props.seo.title)}&url=${encodeURIComponent(props.seo.canonical_url)}`,
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(props.seo.canonical_url)}`,
    whatsapp: `https://api.whatsapp.com/send?text=${encodeURIComponent(props.seo.title + ' ' + props.seo.canonical_url)}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(props.seo.canonical_url)}`,
}));

async function copyLink(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.seo.canonical_url);
        linkCopied.value = true;
        setTimeout(() => { linkCopied.value = false; }, 2000);
    } catch {
        // Fallback for browsers without clipboard API
        const input = document.createElement('input');
        input.value = props.seo.canonical_url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        linkCopied.value = true;
        setTimeout(() => { linkCopied.value = false; }, 2000);
    }
}
</script>

<template>
    <Head :title="seo.title">
        <meta v-if="seo.description" name="description" :content="seo.description" />
        <link rel="canonical" :href="seo.canonical_url" />

        <!-- Open Graph -->
        <meta property="og:type" content="product" />
        <meta property="og:title" :content="seo.title" />
        <meta v-if="seo.description" property="og:description" :content="seo.description" />
        <meta property="og:url" :content="seo.canonical_url" />
        <meta v-if="seo.image" property="og:image" :content="seo.image" />

        <!-- Twitter Card -->
        <meta name="twitter:card" :content="seo.image ? 'summary_large_image' : 'summary'" />
        <meta name="twitter:title" :content="seo.title" />
        <meta v-if="seo.description" name="twitter:description" :content="seo.description" />
        <meta v-if="seo.image" name="twitter:image" :content="seo.image" />

        <!-- Product structured data (JSON-LD) -->
        <component :is="'script'" type="application/ld+json">{{ JSON.stringify({
            '@context': 'https://schema.org/',
            '@type': 'Product',
            name: product.name,
            description: seo.description ?? undefined,
            image: seo.image ?? undefined,
            sku: product.sku,
            offers: {
                '@type': 'Offer',
                priceCurrency: seo.currency,
                price: seo.price ? (seo.price / 100).toFixed(2) : undefined,
                availability: 'https://schema.org/InStock',
                url: seo.canonical_url,
            },
        }) }}</component>
    </Head>

    <component :is="Layout">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <!-- Breadcrumb -->
            <nav class="mb-8">
                <ol class="flex flex-wrap items-center gap-2 text-sm">
                    <li>
                        <Link :href="localePath('/')" class="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                            {{ t('nav.home') }}
                        </Link>
                    </li>
                    <li class="text-gray-400">/</li>
                    <li>
                        <Link :href="localePath('/products')" class="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                            {{ t('nav.products') }}
                        </Link>
                    </li>
                    <li v-if="product.category" class="text-gray-400">/</li>
                    <li v-if="product.category">
                        <Link :href="localePath(`/products?category=${product.category.slug}`)" class="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                            {{ product.category.name }}
                        </Link>
                    </li>
                    <li class="text-gray-400">/</li>
                    <li class="text-slate-900 dark:text-white font-medium truncate">{{ product.name }}</li>
                </ol>
            </nav>

            <div class="lg:grid lg:grid-cols-2 lg:gap-x-12">
                <!-- Image gallery -->
                <div class="lg:row-span-3">
                    <!-- Main image -->
                    <div
                        class="relative aspect-square overflow-hidden rounded-2xl bg-slate-100 dark:bg-navy-800 cursor-zoom-in group"
                        @click="openLightbox(selectedImage)"
                    >
                        <img
                            v-if="productImages.length > 0"
                            :src="productImages[selectedImage]"
                            :alt="product.name"
                            class="h-full w-full object-cover object-center transition-transform duration-300 group-hover:scale-105"
                        />
                        <div
                            v-else
                            class="flex h-full items-center justify-center text-slate-400 dark:text-navy-500"
                        >
                            <svg class="h-24 w-24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>

                        <!-- Sale badge -->
                        <div
                            v-if="discountPercentage"
                            class="absolute top-4 left-4 rounded-full bg-red-500 px-3 py-1.5 text-sm font-bold text-white shadow-lg"
                        >
                            -{{ discountPercentage }}% OFF
                        </div>

                        <!-- Zoom hint -->
                        <div class="absolute bottom-4 right-4 flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
                            </svg>
                            Click to zoom
                        </div>
                    </div>

                    <!-- Thumbnails -->
                    <div
                        v-if="productImages.length > 1"
                        class="mt-4 grid grid-cols-4 gap-3"
                    >
                        <button
                            v-for="(image, index) in productImages"
                            :key="index"
                            @click="selectedImage = index"
                            class="aspect-square overflow-hidden rounded-lg transition-all"
                            :class="[
                                selectedImage === index
                                    ? 'ring-2 ring-brand-500 ring-offset-2 dark:ring-offset-navy-900'
                                    : 'opacity-60 hover:opacity-100'
                            ]"
                        >
                            <img :src="image" :alt="`${product.name} image ${index + 1}`" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>

                <!-- Product info -->
                <div class="mt-10 lg:mt-0">
                    <!-- Category -->
                    <p v-if="product.category" class="text-sm font-medium text-brand-600 dark:text-brand-400 uppercase tracking-wide">
                        {{ product.category.name }}
                    </p>

                    <!-- Name -->
                    <h1 class="mt-2 text-3xl font-bold text-slate-900 dark:text-white sm:text-4xl">
                        {{ product.name }}
                    </h1>

                    <!-- Rating summary + view count -->
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <button
                            v-if="reviewStats.review_count > 0"
                            @click="switchToReviews"
                            class="flex items-center gap-2 hover:opacity-80 transition-opacity"
                        >
                            <StarRating :rating="Math.round(reviewStats.average_rating ?? 0)" size="sm" />
                            <span class="text-sm text-slate-600 dark:text-slate-400">
                                {{ reviewStats.average_rating?.toFixed(1) }}
                                ({{ reviewStats.review_count }})
                            </span>
                        </button>
                        <span v-else class="text-sm text-slate-500 dark:text-slate-400">
                            {{ t('reviews.no_reviews') }}
                        </span>

                        <span v-if="product.view_count" class="text-sm text-slate-500 dark:text-slate-400 flex items-center gap-1">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ product.view_count }} {{ t('products.views') }}
                        </span>
                    </div>

                    <!-- SKU -->
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        SKU: {{ product.sku }}
                    </p>

                    <!-- Sold by (store/tenant) -->
                    <div v-if="product.tenant" class="mt-3">
                        <Link
                            :href="localePath(`/stores/${product.tenant.slug}`)"
                            class="inline-flex items-center gap-2 rounded-lg bg-slate-50 dark:bg-navy-800 border border-slate-200 dark:border-navy-700 px-3 py-2 text-sm transition-colors hover:bg-slate-100 dark:hover:bg-navy-800"
                        >
                            <svg class="h-4 w-4 text-gray-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                            </svg>
                            <span class="text-gray-500 dark:text-gray-400">{{ t('products.sold_by') }}</span>
                            <span class="font-medium text-brand-600 dark:text-brand-400">{{ product.tenant.name }}</span>
                        </Link>
                    </div>

                    <!-- Price -->
                    <div class="mt-6 flex items-baseline gap-4">
                        <span class="text-4xl font-bold text-slate-900 dark:text-white">
                            {{ formatPrice(effectivePrice) }}
                        </span>
                        <span
                            v-if="product.sale_price"
                            class="text-xl text-slate-400 line-through"
                        >
                            {{ formatPrice(product.price_cents) }}
                        </span>
                        <span
                            v-if="discountPercentage"
                            class="rounded-full bg-red-100 dark:bg-red-900/50 px-3 py-1 text-sm font-semibold text-red-600 dark:text-red-400"
                        >
                            Save {{ discountPercentage }}%
                        </span>
                    </div>

                    <!-- Variant Selector -->
                    <div v-if="hasVariants" class="mt-6 space-y-4">
                        <div v-for="type in attributeTypes" :key="type.id">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ type.name }}</span>
                                <span v-if="selectedAttributes[type.id]" class="text-sm text-slate-500 dark:text-slate-400">
                                    — {{ type.values.find(v => v.id === selectedAttributes[type.id])?.value }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <!-- Color swatch -->
                                <template v-if="type.slug === 'color'">
                                    <button
                                        v-for="value in type.values"
                                        :key="value.id"
                                        :title="value.value"
                                        :disabled="!isValueAvailable(type.id, value.id)"
                                        @click="selectAttribute(type.id, value.id)"
                                        class="relative h-9 w-9 rounded-full border-2 transition-all focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                                        :class="{
                                            'border-brand-500 ring-2 ring-brand-500 ring-offset-1': selectedAttributes[type.id] === value.id,
                                            'border-slate-300 dark:border-navy-600 hover:border-slate-400': selectedAttributes[type.id] !== value.id,
                                            'opacity-40 cursor-not-allowed': !isValueAvailable(type.id, value.id),
                                        }"
                                        :style="value.metadata?.hex ? { backgroundColor: value.metadata.hex } : {}"
                                    >
                                        <span v-if="!value.metadata?.hex" class="text-xs font-medium text-slate-700 dark:text-white">
                                            {{ value.value.charAt(0) }}
                                        </span>
                                        <!-- Strikethrough overlay for unavailable -->
                                        <span
                                            v-if="!isValueAvailable(type.id, value.id)"
                                            class="absolute inset-0 flex items-center justify-center"
                                        >
                                            <span class="absolute w-full h-px bg-red-400 rotate-45" />
                                        </span>
                                    </button>
                                </template>

                                <!-- Text option button -->
                                <template v-else>
                                    <button
                                        v-for="value in type.values"
                                        :key="value.id"
                                        :disabled="!isValueAvailable(type.id, value.id)"
                                        @click="selectAttribute(type.id, value.id)"
                                        class="relative min-w-[3rem] rounded-lg border px-3 py-1.5 text-sm font-medium transition-all focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                                        :class="{
                                            'border-brand-500 bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300': selectedAttributes[type.id] === value.id,
                                            'border-slate-300 dark:border-navy-600 text-slate-700 dark:text-slate-300 hover:border-slate-400 dark:hover:border-navy-500': selectedAttributes[type.id] !== value.id,
                                            'opacity-40 cursor-not-allowed line-through': !isValueAvailable(type.id, value.id),
                                        }"
                                    >
                                        {{ value.value }}
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Variant not available notice -->
                        <p v-if="hasVariants && !selectedVariant && Object.keys(selectedAttributes).length === attributeTypes.length" class="text-sm text-red-600 dark:text-red-400">
                            This combination is not available.
                        </p>

                        <!-- Selected variant SKU -->
                        <p v-if="selectedVariant" class="text-xs text-slate-500 dark:text-slate-400">
                            SKU: {{ selectedVariant.sku }}
                        </p>
                    </div>

                    <!-- Stock status -->
                    <div class="mt-4 flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 text-sm font-medium"
                            :class="stockStatus.class"
                        >
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="{
                                    'bg-green-500': stockStatus.available && (!product.stock || (product.stock.available ?? product.stock.quantity) > 5),
                                    'bg-yellow-500': stockStatus.available && product.stock && (product.stock.available ?? product.stock.quantity) <= 5,
                                    'bg-red-500': !stockStatus.available,
                                }"
                            />
                            {{ stockStatus.text }}
                        </span>
                    </div>

                    <!-- Add to cart section -->
                    <div v-if="stockStatus.available" class="mt-8 space-y-4">
                        <div class="flex items-center gap-4">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t('products.quantity') }}
                            </label>
                            <QuantityStepper
                                v-model="quantity"
                                :min="1"
                                :max="maxQuantity"
                                size="md"
                            />
                        </div>

                        <!-- Error message -->
                        <div v-if="cartError" class="rounded-lg bg-red-50 dark:bg-red-900/50 p-4 border border-red-200 dark:border-red-800">
                            <p class="text-sm text-red-700 dark:text-red-200">{{ cartError.message }}</p>
                        </div>

                        <!-- Success message -->
                        <Transition
                            enter-active-class="duration-200 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-2"
                        >
                            <div v-if="addedToCart" class="rounded-lg bg-green-50 dark:bg-green-900/50 p-4 border border-green-200 dark:border-green-800">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                    </svg>
                                    <p class="text-sm font-medium text-green-700 dark:text-green-200">{{ t('products.added') }}</p>
                                    <Link :href="localePath('/cart')" class="ml-auto text-sm font-medium text-green-600 dark:text-green-400 hover:underline">
                                        {{ t('products.view_cart') }}
                                    </Link>
                                </div>
                            </div>
                        </Transition>

                        <div class="flex gap-3">
                            <button
                                @click="handleAddToCart"
                                :disabled="cartLoading || (hasVariants && !selectedVariant)"
                                :title="hasVariants && !selectedVariant ? 'Please select options above' : undefined"
                                class="flex-1 flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-8 py-4 text-base font-semibold text-white shadow-lg shadow-brand-500/25 hover:bg-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <Spinner v-if="cartLoading" size="sm" color="white" />
                                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                                {{ cartLoading ? t('common.loading') : t('products.add_to_cart') }}
                            </button>

                            <!-- Wishlist button -->
                            <button
                                @click="toggleWishlist(product.id)"
                                class="flex items-center justify-center rounded-xl border-2 px-5 py-4 transition-all"
                                :class="isInWishlist(product.id)
                                    ? 'border-red-500 bg-red-50 dark:bg-red-900/20 text-red-500'
                                    : 'border-slate-300 dark:border-navy-700 text-slate-500 hover:border-red-300 hover:text-red-500'"
                            >
                                <svg
                                    class="h-6 w-6"
                                    :fill="isInWishlist(product.id) ? 'currentColor' : 'none'"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Out of stock message -->
                    <div v-else class="mt-8">
                        <button
                            disabled
                            class="w-full flex items-center justify-center gap-2 rounded-xl bg-slate-200 dark:bg-navy-800 px-8 py-4 text-base font-semibold text-slate-500 dark:text-slate-400 cursor-not-allowed"
                        >
                            {{ t('products.out_of_stock') }}
                        </button>
                    </div>

                    <!-- Trust badges -->
                    <div class="mt-8 grid grid-cols-3 gap-4 border-t border-slate-100 dark:border-navy-800/60 pt-8">
                        <div v-for="badge in trustBadges" :key="badge.text" class="flex flex-col items-center text-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 dark:bg-navy-800">
                                <svg v-if="badge.icon === 'shield'" class="h-5 w-5 text-slate-600 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                <svg v-else-if="badge.icon === 'truck'" class="h-5 w-5 text-slate-600 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                </svg>
                                <svg v-else class="h-5 w-5 text-slate-600 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </div>
                            <span class="mt-2 text-xs font-medium text-slate-600 dark:text-slate-400">{{ badge.text }}</span>
                        </div>
                    </div>

                    <!-- Social share -->
                    <div class="mt-6 flex items-center gap-3 border-t border-slate-100 dark:border-navy-800/60 pt-6">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400 shrink-0">Share:</span>

                        <!-- X / Twitter -->
                        <a
                            :href="shareLinks.twitter"
                            target="_blank"
                            rel="noopener noreferrer"
                            title="Share on X (Twitter)"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 dark:border-navy-700 text-slate-500 dark:text-slate-400 hover:border-slate-900 hover:text-slate-900 dark:hover:border-white dark:hover:text-white transition-colors"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.254 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                            </svg>
                        </a>

                        <!-- Facebook -->
                        <a
                            :href="shareLinks.facebook"
                            target="_blank"
                            rel="noopener noreferrer"
                            title="Share on Facebook"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 dark:border-navy-700 text-slate-500 dark:text-slate-400 hover:border-blue-600 hover:text-blue-600 dark:hover:border-blue-400 dark:hover:text-blue-400 transition-colors"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                            </svg>
                        </a>

                        <!-- WhatsApp -->
                        <a
                            :href="shareLinks.whatsapp"
                            target="_blank"
                            rel="noopener noreferrer"
                            title="Share on WhatsApp"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 dark:border-navy-700 text-slate-500 dark:text-slate-400 hover:border-green-500 hover:text-green-500 dark:hover:border-green-400 dark:hover:text-green-400 transition-colors"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                            </svg>
                        </a>

                        <!-- LinkedIn -->
                        <a
                            :href="shareLinks.linkedin"
                            target="_blank"
                            rel="noopener noreferrer"
                            title="Share on LinkedIn"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 dark:border-navy-700 text-slate-500 dark:text-slate-400 hover:border-blue-700 hover:text-blue-700 dark:hover:border-blue-400 dark:hover:text-blue-400 transition-colors"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                            </svg>
                        </a>

                        <!-- Copy link -->
                        <button
                            @click="copyLink"
                            :title="linkCopied ? 'Copied!' : 'Copy link'"
                            class="flex h-8 w-8 items-center justify-center rounded-full border transition-colors"
                            :class="linkCopied
                                ? 'border-green-500 text-green-500 dark:border-green-400 dark:text-green-400'
                                : 'border-slate-200 dark:border-navy-700 text-slate-500 dark:text-slate-400 hover:border-slate-700 hover:text-slate-700 dark:hover:border-slate-300 dark:hover:text-slate-300'"
                        >
                            <svg v-if="linkCopied" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabbed content -->
            <div class="mt-16">
                <div class="border-b border-slate-200 dark:border-navy-700">
                    <nav class="flex gap-8">
                        <button
                            @click="activeTab = 'description'"
                            class="py-4 text-sm font-medium border-b-2 transition-colors"
                            :class="activeTab === 'description'
                                ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                        >
                            {{ t('products.description') }}
                        </button>
                        <button
                            @click="activeTab = 'specifications'"
                            class="py-4 text-sm font-medium border-b-2 transition-colors"
                            :class="activeTab === 'specifications'
                                ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                        >
                            {{ t('products.specifications') }}
                        </button>
                        <button
                            @click="switchToReviews"
                            class="py-4 text-sm font-medium border-b-2 transition-colors flex items-center gap-2"
                            :class="activeTab === 'reviews'
                                ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                        >
                            {{ t('reviews.title') }}
                            <span
                                v-if="reviewStats.review_count > 0"
                                class="rounded-full bg-slate-100 dark:bg-navy-800 px-2 py-0.5 text-xs font-medium"
                            >
                                {{ reviewStats.review_count }}
                            </span>
                        </button>
                        <button
                            @click="switchToQuestions"
                            class="py-4 text-sm font-medium border-b-2 transition-colors flex items-center gap-2"
                            :class="activeTab === 'questions'
                                ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'"
                        >
                            Q&amp;A
                            <span
                                v-if="questionCount > 0"
                                class="rounded-full bg-slate-100 dark:bg-navy-800 px-2 py-0.5 text-xs font-medium"
                            >
                                {{ questionCount }}
                            </span>
                        </button>
                    </nav>
                </div>

                <div class="py-8">
                    <!-- Description tab -->
                    <div v-if="activeTab === 'description'">
                        <div class="prose prose-gray dark:prose-invert max-w-none">
                            <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                                {{ product.description || product.short_description || 'No description available for this product.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Specifications tab -->
                    <div v-if="activeTab === 'specifications'">
                        <dl class="divide-y divide-slate-100 dark:divide-navy-800/60">
                            <div class="py-4 grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">SKU</dt>
                                <dd class="col-span-2 text-sm text-slate-900 dark:text-white">{{ product.sku }}</dd>
                            </div>
                            <div v-if="product.category" class="py-4 grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ t('products.specifications') === 'Specifications' ? 'Category' : t('products.specifications') }}</dt>
                                <dd class="col-span-2 text-sm text-slate-900 dark:text-white">{{ product.category.name }}</dd>
                            </div>
                            <div class="py-4 grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ t('products.in_stock') }}</dt>
                                <dd class="col-span-2 text-sm" :class="stockStatus.class">{{ stockStatus.text }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Q&A tab -->
                    <div v-if="activeTab === 'questions'">
                        <!-- Ask a question form -->
                        <div class="mb-8 rounded-xl bg-white dark:bg-navy-900/60 border border-slate-100 dark:border-navy-800/60 p-6">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-4">Ask a question</h3>
                            <div v-if="$page.props.auth?.user">
                                <textarea
                                    v-model="questionBody"
                                    rows="3"
                                    placeholder="What would you like to know about this product?"
                                    class="block w-full rounded-xl border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 resize-none"
                                />
                                <button
                                    @click="submitQuestion"
                                    :disabled="questionBody.trim().length < 10 || questionSubmitting"
                                    class="mt-3 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold px-5 py-2.5 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ questionSubmitting ? 'Posting…' : 'Post Question' }}
                                </button>
                            </div>
                            <p v-else class="text-sm text-slate-500 dark:text-slate-400">
                                <Link :href="`/${$page.props.locale ?? 'en'}/login`" class="text-brand-600 hover:underline">Sign in</Link>
                                to ask a question.
                            </p>
                        </div>

                        <!-- Questions list -->
                        <div v-if="questionsLoading && !questionsLoaded" class="flex justify-center py-12">
                            <Spinner size="lg" />
                        </div>

                        <div v-else-if="questions.length > 0" class="space-y-6">
                            <div
                                v-for="q in questions"
                                :key="q.id"
                                class="rounded-xl bg-white dark:bg-navy-900/60 border border-slate-100 dark:border-navy-800/60 p-5"
                            >
                                <!-- Question -->
                                <div class="flex gap-3">
                                    <div class="flex-shrink-0 mt-0.5">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 text-xs font-bold">Q</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ q.body }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ q.author_name }} · {{ q.created_at.slice(0, 10) }}</p>
                                    </div>
                                </div>

                                <!-- Answers -->
                                <div v-if="q.answers.length > 0" class="mt-4 space-y-3 pl-10">
                                    <div
                                        v-for="a in q.answers"
                                        :key="a.id"
                                        class="flex gap-3"
                                    >
                                        <div class="flex-shrink-0 mt-0.5">
                                            <span
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                                                :class="a.is_vendor_answer
                                                    ? 'bg-accent-100 dark:bg-accent-900/30 text-accent-700 dark:text-accent-400'
                                                    : 'bg-slate-100 dark:bg-navy-800 text-slate-500 dark:text-slate-400'"
                                            >A</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-slate-700 dark:text-slate-300">{{ a.body }}</p>
                                            <p class="mt-1 text-xs text-slate-400">
                                                <span v-if="a.is_vendor_answer" class="text-accent-600 dark:text-accent-400 font-medium mr-1">Seller</span>
                                                {{ a.author_name }} · {{ a.created_at.slice(0, 10) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Answer form for logged-in users -->
                                <div v-if="$page.props.auth?.user" class="mt-4 pl-10">
                                    <div v-if="!expandedAnswers[q.id]">
                                        <button
                                            @click="expandedAnswers[q.id] = true"
                                            class="text-xs text-brand-600 hover:text-brand-500 font-medium transition-colors"
                                        >
                                            + Write an answer
                                        </button>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <textarea
                                            v-model="answerBody[q.id]"
                                            rows="2"
                                            placeholder="Share your answer…"
                                            class="block w-full rounded-xl border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 px-4 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 resize-none"
                                        />
                                        <div class="flex gap-2">
                                            <button
                                                @click="submitAnswer(q)"
                                                :disabled="!answerBody[q.id]?.trim() || answerSubmitting[q.id]"
                                                class="rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-xs font-semibold px-4 py-2 transition-colors disabled:opacity-50"
                                            >
                                                {{ answerSubmitting[q.id] ? 'Posting…' : 'Post Answer' }}
                                            </button>
                                            <button
                                                @click="expandedAnswers[q.id] = false"
                                                class="rounded-lg text-slate-500 hover:text-slate-700 text-xs font-medium px-3 py-2 transition-colors"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                            </svg>
                            <h3 class="mt-4 text-base font-medium text-slate-900 dark:text-white">No questions yet</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Be the first to ask about this product.</p>
                        </div>
                    </div>

                    <!-- Reviews tab -->
                    <div v-if="activeTab === 'reviews'">
                        <div class="lg:grid lg:grid-cols-3 lg:gap-8">
                            <!-- Left: Rating summary -->
                            <div class="mb-8 lg:mb-0">
                                <div class="rounded-xl bg-white dark:bg-navy-900/60 border border-slate-100 dark:border-navy-800/60 p-6">
                                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                                        {{ t('reviews.average') }}
                                    </h3>

                                    <div v-if="reviewStats.review_count > 0" class="text-center">
                                        <p class="text-5xl font-bold text-slate-900 dark:text-white">
                                            {{ reviewStats.average_rating?.toFixed(1) }}
                                        </p>
                                        <StarRating
                                            :rating="Math.round(reviewStats.average_rating ?? 0)"
                                            size="lg"
                                            class="mt-2 justify-center"
                                        />
                                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                            {{ reviewStats.review_count }} {{ reviewStats.review_count === 1 ? 'review' : 'reviews' }}
                                        </p>
                                        <RatingDistribution
                                            v-if="reviewStats.distribution"
                                            :distribution="reviewStats.distribution"
                                            :total-count="reviewStats.review_count"
                                            class="mt-4"
                                        />
                                    </div>
                                    <div v-else class="text-center py-4">
                                        <p class="text-slate-500 dark:text-slate-400 text-sm">
                                            {{ t('reviews.no_reviews') }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Write review form or sign-in prompt -->
                                <div class="mt-6">
                                    <ReviewForm
                                        v-if="isAuthenticated && !hasAlreadyReviewed"
                                        :product-slug="product.slug"
                                        @submitted="handleReviewSubmitted"
                                    />
                                    <div
                                        v-else-if="isAuthenticated && hasAlreadyReviewed"
                                        class="rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800/60 p-6 text-center"
                                    >
                                        <svg class="mx-auto h-8 w-8 text-green-500 dark:text-green-400 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <p class="text-sm font-medium text-green-700 dark:text-green-400">
                                            {{ t('reviews.already_reviewed') || 'You have already reviewed this product.' }}
                                        </p>
                                    </div>
                                    <div v-else class="rounded-xl bg-white dark:bg-navy-900/60 border border-slate-100 dark:border-navy-800/60 p-6 text-center">
                                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">
                                            {{ t('reviews.sign_in_to_review') }}
                                        </p>
                                        <Link
                                            :href="localePath('/login')"
                                            class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-400 transition-colors"
                                        >
                                            {{ t('auth.sign_in') }}
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Reviews list -->
                            <div class="lg:col-span-2">
                                <!-- Loading state -->
                                <div v-if="reviewsLoading && !reviewsLoaded" class="flex justify-center py-12">
                                    <Spinner size="lg" />
                                </div>

                                <!-- Reviews list -->
                                <div v-else-if="reviews.length > 0" class="space-y-6">
                                    <ReviewCard
                                        v-for="review in reviews"
                                        :key="review.id"
                                        :review="review"
                                        :current-user-id="$page.props.auth?.user?.id"
                                        :is-authenticated="!!$page.props.auth?.user"
                                        @reply-added="handleReplyAdded"
                                        @vote-updated="handleVoteUpdated"
                                    />

                                    <!-- Load more -->
                                    <div v-if="reviewsNextPage" class="text-center pt-4">
                                        <button
                                            @click="loadMoreReviews"
                                            :disabled="reviewsLoading"
                                            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-800/60 px-6 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-navy-800 disabled:opacity-50 transition-colors"
                                        >
                                            <Spinner v-if="reviewsLoading" size="sm" />
                                            {{ t('reviews.load_more') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Empty reviews state -->
                                <div v-else class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                                    </svg>
                                    <h3 class="mt-4 text-lg font-medium text-slate-900 dark:text-white">
                                        {{ t('reviews.no_reviews') }}
                                    </h3>
                                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                        {{ t('reviews.be_first') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Recommendations: Customers Also Bought -->
            <div class="mt-16">
                <Deferred data="recommendations">
                    <template #fallback>
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Customers Also Bought</h2>
                        <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            <div
                                v-for="n in 4"
                                :key="n"
                                class="animate-pulse rounded-2xl border border-slate-100 bg-white dark:border-navy-800/60 dark:bg-navy-900/60 overflow-hidden"
                            >
                                <div class="h-48 bg-slate-200 dark:bg-navy-800" />
                                <div class="p-4 space-y-3">
                                    <div class="h-4 rounded bg-slate-200 dark:bg-navy-800 w-3/4" />
                                    <div class="h-3 rounded bg-slate-200 dark:bg-navy-800 w-1/2" />
                                    <div class="h-5 rounded bg-slate-200 dark:bg-navy-800 w-1/3" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-if="recommendations && recommendations.length > 0">
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Customers Also Bought</h2>
                        <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            <ProductCard
                                v-for="rec in recommendations.slice(0, 4)"
                                :key="rec.id"
                                :product="rec"
                            />
                        </div>
                    </template>
                </Deferred>
            </div>

            <!-- Related products -->
            <div v-if="relatedProducts.length > 0" class="mt-16">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ t("products.related") }}</h2>
                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <ProductCard
                        v-for="related in relatedProducts.slice(0, 4)"
                        :key="related.id"
                        :product="related"
                    />
                </div>
            </div>
        </div>

        <!-- Sticky mobile add-to-cart bar -->
        <div
            v-if="stockStatus.available"
            class="fixed bottom-0 left-0 right-0 z-40 bg-white dark:bg-navy-950 border-t border-slate-200 dark:border-navy-800 p-4 lg:hidden"
        >
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <p class="text-lg font-bold text-slate-900 dark:text-white">{{ formatPrice(effectivePrice) }}</p>
                    <p class="text-sm" :class="stockStatus.class">{{ stockStatus.text }}</p>
                </div>
                <button
                    @click="handleAddToCart"
                    :disabled="cartLoading"
                    class="flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-3 text-sm font-semibold text-white hover:bg-brand-400 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <Spinner v-if="cartLoading" size="sm" color="white" />
                    <span>{{ cartLoading ? t('common.loading') : t('products.add_to_cart') }}</span>
                </button>
            </div>
        </div>

        <!-- Add padding at bottom for mobile sticky bar -->
        <div class="h-24 lg:hidden" />

        <!-- Image lightbox -->
        <ImageLightbox
            :images="productImages"
            :initial-index="selectedImage"
            :show="lightboxOpen"
            :alt="product.name"
            @close="lightboxOpen = false"
        />
    </component>
</template>
