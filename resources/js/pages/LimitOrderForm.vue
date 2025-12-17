<script setup lang="ts">
import { apiRequest, getAuthToken } from '@/lib/api';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface Toast {
    id: number;
    type: 'success' | 'error' | 'info';
    message: string;
}

const symbol = ref<'BTC' | 'ETH'>('BTC');
const side = ref<'buy' | 'sell'>('buy');
const price = ref('');
const amount = ref('');
const loading = ref(false);
const errors = ref<Record<string, string>>({});
const FEE_RATE = 0.015;
const toasts = ref<Toast[]>([]);
let toastIdCounter = 0;

const showToast = (type: 'success' | 'error' | 'info', message: string) => {
    const id = ++toastIdCounter;
    toasts.value.push({ id, type, message });
    setTimeout(() => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }, 4000);
};

const removeToast = (id: number) => {
    toasts.value = toasts.value.filter((t) => t.id !== id);
};

const volumePreview = computed(() => {
    const p = parseFloat(price.value) || 0;
    const a = parseFloat(amount.value) || 0;
    return (p * a).toFixed(2);
});

const feePreview = computed(() => {
    const volume = parseFloat(volumePreview.value) || 0;
    return (volume * FEE_RATE).toFixed(2);
});

const handleViewOrders = () => {
    try {
        router.visit('/orders');
    } catch (error) {
        console.error('Navigation error:', error);
        if (typeof window !== 'undefined') {
            window.location.href = '/orders';
        }
    }
};

const placeOrder = async () => {
    loading.value = true;
    errors.value = {};

    try {
        const response = await apiRequest('/v1/orders', {
            method: 'POST',
            body: JSON.stringify({
                symbol: symbol.value,
                side: side.value,
                price: parseFloat(price.value),
                amount: parseFloat(amount.value),
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 422) {
                const formattedErrors: Record<string, string> = {};
                if (data.errors) {
                    Object.keys(data.errors).forEach((key) => {
                        const errorValue = data.errors[key];
                        formattedErrors[key] = Array.isArray(errorValue) ? errorValue[0] : errorValue;
                    });
                }
                errors.value = formattedErrors;
            } else {
                showToast('error', data.message || 'An error occurred');
            }
            loading.value = false;
            return;
        }

        showToast('success', `${side.value.toUpperCase()} order placed successfully!`);
        price.value = '';
        amount.value = '';
    } catch (error) {
        console.error(error);
        showToast('error', 'An error occurred. Please try again.');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    const token = getAuthToken();
    if (!token) {
        if (typeof window !== 'undefined') {
            window.location.href = '/login';
        }
    }
});
</script>

<template>
    <Head title="Place Order" />

    <div class="fixed top-4 right-4 z-50 space-y-2">
        <transition-group name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :class="[
                    'flex items-center gap-3 rounded-lg px-4 py-3 shadow-lg transition-all duration-300',
                    toast.type === 'success' && 'bg-green-500 text-white',
                    toast.type === 'error' && 'bg-red-500 text-white',
                    toast.type === 'info' && 'bg-blue-500 text-white',
                ]"
            >
                <span class="text-sm font-medium">{{ toast.message }}</span>
                <button @click="removeToast(toast.id)" class="ml-2 text-white/80 hover:text-white">✕</button>
            </div>
        </transition-group>
    </div>

    <div class="min-h-screen bg-gray-50 p-6 dark:bg-gray-900">
        <div class="mx-auto max-w-2xl">
            <div class="rounded-lg bg-white p-8 shadow-md dark:bg-gray-800">
                <div class="mb-6 flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Place Limit Order</h1>
                    <div class="flex gap-4">
                        <button
                            @click="handleViewOrders"
                            class="rounded-md bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-gray-500 dark:hover:bg-gray-600"
                        >
                            View Orders
                        </button>
                        <button
                            @click="router.visit('/logout')"
                            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                        >
                            Logout
                        </button>
                    </div>
                </div>

                <form @submit.prevent="placeOrder" class="space-y-6">
                    <!-- Symbol Selection -->
                    <div>
                        <label for="symbol" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Symbol </label>
                        <select
                            id="symbol"
                            v-model="symbol"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
                        >
                            <option value="BTC">BTC</option>
                            <option value="ETH">ETH</option>
                        </select>
                        <p v-if="errors.symbol" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ errors.symbol }}
                        </p>
                    </div>

                    <!-- Side Selection -->
                    <div>
                        <label for="side" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Side </label>
                        <select
                            id="side"
                            v-model="side"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
                        >
                            <option value="buy">Buy</option>
                            <option value="sell">Sell</option>
                        </select>
                        <p v-if="errors.side" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ errors.side }}
                        </p>
                    </div>

                    <!-- Price Input -->
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Price (USD) </label>
                        <input
                            id="price"
                            v-model="price"
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            required
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
                            placeholder="Enter price"
                        />
                        <p v-if="errors.price" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ errors.price }}
                        </p>
                    </div>

                    <!-- Amount Input -->
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Amount </label>
                        <input
                            id="amount"
                            v-model="amount"
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            required
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder-gray-500 focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
                            placeholder="Enter amount"
                        />
                        <p v-if="errors.amount" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ errors.amount }}
                        </p>
                    </div>

                    <!-- Volume Preview -->
                    <div v-if="price && amount" class="rounded-md bg-gray-100 p-4 dark:bg-gray-700">
                        <h3 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Order Preview</h3>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Total Volume:</span>
                                <span class="font-semibold text-gray-900 dark:text-white">${{ volumePreview }}</span>
                            </div>
                            <div v-if="side === 'sell'" class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Est. Fee (1.5%):</span>
                                <span class="text-red-600 dark:text-red-400">-${{ feePreview }}</span>
                            </div>
                            <div v-if="side === 'sell'" class="flex justify-between border-t border-gray-200 pt-1 dark:border-gray-600">
                                <span class="text-gray-600 dark:text-gray-400">You'll receive:</span>
                                <span class="font-semibold text-green-600 dark:text-green-400">
                                    ${{ (parseFloat(volumePreview) - parseFloat(feePreview)).toFixed(2) }}
                                </span>
                            </div>
                            <div v-if="side === 'buy'" class="flex justify-between border-t border-gray-200 pt-1 dark:border-gray-600">
                                <span class="text-gray-600 dark:text-gray-400">You'll pay:</span>
                                <span class="font-semibold text-gray-900 dark:text-white">${{ volumePreview }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:bg-indigo-500 dark:hover:bg-indigo-600 dark:focus:ring-indigo-400"
                        >
                            <span v-if="loading">Placing Order...</span>
                            <span v-else>Place Order</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>
