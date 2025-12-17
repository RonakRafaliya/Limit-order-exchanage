<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { apiRequest, getAuthToken } from '@/lib/api';
import { getEcho } from '@/lib/echo';

interface Asset {
    id: number;
    symbol: string;
    amount: string;
    locked_amount: string;
}

interface Order {
    id: number;
    symbol: string;
    side: string;
    price: string;
    amount: string;
    remaining_amount: string;
    status: number;
    created_at: string;
    updated_at: string;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Toast {
    id: number;
    type: 'success' | 'error' | 'info';
    message: string;
}

const selectedSymbol = ref<'BTC' | 'ETH'>('BTC');
const usdBalance = ref('0');
const assets = ref<Asset[]>([]);
const userOrders = ref<Order[]>([]);
const orderbook = ref<Order[]>([]);
const user = ref<User | null>(null);
const loading = ref(false);
const error = ref('');
let userChannelSubscription: any = null;
let orderbookChannelSubscription: any = null;

// Filters
const filterSymbol = ref<'all' | 'BTC' | 'ETH'>('all');
const filterSide = ref<'all' | 'buy' | 'sell'>('all');
const filterStatus = ref<'all' | 1 | 2 | 3>('all');

// Toast notifications
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

// Filtered orders
const filteredOrders = computed(() => {
    return userOrders.value.filter((order) => {
        if (filterSymbol.value !== 'all' && order.symbol !== filterSymbol.value) return false;
        if (filterSide.value !== 'all' && order.side !== filterSide.value) return false;
        if (filterStatus.value !== 'all' && order.status !== filterStatus.value) return false;
        return true;
    });
});

const statusLabels: Record<number, string> = {
    1: 'Open',
    2: 'Filled',
    3: 'Cancelled',
};

const statusColors: Record<number, string> = {
    1: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200',
    2: 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-200',
    3: 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-200',
};

const fetchUser = async () => {
    try {
        const response = await apiRequest('/user');
        if (!response.ok) {
            throw new Error('Failed to fetch user');
        }
        const data = await response.json();
        user.value = data;
    } catch (err) {
        console.error('Error fetching user:', err);
        throw err;
    }
};

const fetchProfile = async () => {
    try {
        const response = await apiRequest('/v1/profile');
        if (!response.ok) {
            throw new Error('Failed to fetch profile');
        }
        const data = await response.json();
        usdBalance.value = data.usd_balance || '0';
        assets.value = data.assets || [];
    } catch (err) {
        console.error('Error fetching profile:', err);
        throw err;
    }
};

const fetchUserOrders = async () => {
    try {
        const response = await apiRequest('/v1/orders?user_orders=true');
        if (!response.ok) {
            throw new Error('Failed to fetch orders');
        }
        const data = await response.json();
        userOrders.value = data || [];
    } catch (err) {
        console.error('Error fetching user orders:', err);
        throw err;
    }
};

const fetchOrderbook = async () => {
    try {
        const response = await apiRequest(`/v1/orders?symbol=${selectedSymbol.value}`);
        if (!response.ok) {
            throw new Error('Failed to fetch orderbook');
        }
        const data = await response.json();
        orderbook.value = data || [];
    } catch (err) {
        console.error('Error fetching orderbook:', err);
        throw err;
    }
};

const cancelOrder = async (orderId: number) => {
    if (!confirm('Are you sure you want to cancel this order?')) {
        return;
    }

    try {
        const response = await apiRequest(`/v1/orders/${orderId}/cancel`, {
            method: 'POST',
        });

        if (response.ok) {
            showToast('success', 'Order cancelled successfully');
            await Promise.all([fetchUserOrders(), fetchProfile(), fetchOrderbook()]);
        } else {
            const data = await response.json();
            showToast('error', data.message || 'Failed to cancel order');
        }
    } catch (err) {
        console.error('Error cancelling order:', err);
        showToast('error', 'Failed to cancel order');
    }
};

const loadData = async () => {
    loading.value = true;
    error.value = '';
    try {
        await Promise.all([fetchUser(), fetchProfile(), fetchUserOrders(), fetchOrderbook()]);
    } catch (err: any) {
        if (err?.message !== 'Unauthorized') {
            error.value = 'Failed to load data. Please try again.';
        }
    } finally {
        loading.value = false;
    }
};

const handleOrderMatched = async () => {
    showToast('success', 'Order matched! Your balance has been updated.');
    await Promise.all([fetchProfile(), fetchUserOrders(), fetchOrderbook()]);
};

const setupOrderbookChannel = () => {
    const echo = getEcho();
    if (!echo) return;

    orderbookChannelSubscription = echo
        .channel(`orderbook.${selectedSymbol.value}`)
        .listen('.orderbook.updated', () => {
            console.log('ORDERBOOK UPDATED (realtime)');
            fetchOrderbook();
        });
};

const switchOrderbookChannel = () => {
    const echo = getEcho();
    if (!echo) return;

    if (orderbookChannelSubscription) {
        echo.leave(`orderbook.BTC`);
        echo.leave(`orderbook.ETH`);
    }

    setupOrderbookChannel();
};

onMounted(async () => {
    const token = getAuthToken();
    if (!token) {
        if (typeof window !== 'undefined') {
            window.location.href = '/login';
        }
        return;
    }

    try {
        await loadData();

        const echo = getEcho();
        if (echo) {
            try {
                const userResponse = await apiRequest('/user');
                if (userResponse.ok) {
                    const userData = await userResponse.json();
                    const userId = userData.id;

                    userChannelSubscription = echo.private(`user.${userId}`).listen('.order.matched', () => {
                        console.log('ORDER MATCHED (realtime)');
                        handleOrderMatched();
                    });
                }
            } catch (err) {
                console.error('Error setting up user channel:', err);
            }

            setupOrderbookChannel();
        }
    } catch (err) {
        console.error('Error loading data:', err);
    }
});

onUnmounted(() => {
    const echo = getEcho();
    try {
        if (userChannelSubscription && typeof userChannelSubscription.stopListening === 'function') {
            userChannelSubscription.stopListening('.order.matched');
        }
        if (echo) {
            echo.leave(`orderbook.BTC`);
            echo.leave(`orderbook.ETH`);
        }
    } catch (err) {
        console.debug('Echo cleanup:', err);
    }
});

const watchSymbol = () => {
    fetchOrderbook();
    switchOrderbookChannel();
};
</script>

<template>
    <Head title="Orders & Wallet Overview" />

    <!-- Toast Notifications -->
    <div class="fixed right-4 top-4 z-50 space-y-2">
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
                <button @click="removeToast(toast.id)" class="ml-2 text-white/80 hover:text-white">
                    ✕
                </button>
            </div>
        </transition-group>
    </div>

    <div class="min-h-screen bg-gray-50 p-6 dark:bg-gray-900">
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Orders & Wallet Overview</h1>
                    <p v-if="user" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Logged in as {{ user.name }}
                    </p>
                </div>
                <div class="flex gap-4">
                    <button
                        @click="router.visit('/place-order')"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                    >
                        Place Order
                    </button>
                    <button
                        @click="router.visit('/logout')"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                    >
                        Logout
                    </button>
                </div>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="rounded-md bg-red-50 p-4 dark:bg-red-900/20">
                <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ error }}</p>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="text-center text-gray-600 dark:text-gray-400">Loading...</div>

            <!-- Main Content -->
            <div v-else class="grid gap-6 md:grid-cols-3">
                <!-- Left Column: Balances & Orders -->
                <div class="space-y-6 md:col-span-2">
                    <!-- USD and Asset Balances -->
                    <div class="rounded-lg bg-white p-6 shadow-md dark:bg-gray-800">
                        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">Wallet Balances</h2>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-400">USD Balance</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">
                                    ${{ parseFloat(usdBalance).toFixed(2) }}
                                </span>
                            </div>
                            <div v-for="asset in assets" :key="asset.id" class="flex items-center justify-between">
                                <span class="text-gray-600 dark:text-gray-400">{{ asset.symbol }}</span>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-gray-900 dark:text-white">
                                        {{ parseFloat(asset.amount).toFixed(2) }}
                                    </div>
                                    <div v-if="parseFloat(asset.locked_amount) > 0" class="text-xs text-gray-500">
                                        Locked: {{ parseFloat(asset.locked_amount).toFixed(2) }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="assets.length === 0" class="text-sm text-gray-500">
                                No assets yet
                            </div>
                        </div>
                    </div>

                    <!-- All Past Orders -->
                    <div class="rounded-lg bg-white p-6 shadow-md dark:bg-gray-800">
                        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">My Orders</h2>

                        <!-- Filters -->
                        <div class="mb-4 flex flex-wrap gap-3">
                            <select
                                v-model="filterSymbol"
                                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="all">All Symbols</option>
                                <option value="BTC">BTC</option>
                                <option value="ETH">ETH</option>
                            </select>
                            <select
                                v-model="filterSide"
                                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="all">All Sides</option>
                                <option value="buy">Buy</option>
                                <option value="sell">Sell</option>
                            </select>
                            <select
                                v-model="filterStatus"
                                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="all">All Status</option>
                                <option :value="1">Open</option>
                                <option :value="2">Filled</option>
                                <option :value="3">Cancelled</option>
                            </select>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                            Symbol
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                            Side
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                            Price
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                            Amount
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                            Volume
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                            Status
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <tr v-for="order in filteredOrders" :key="order.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-white">
                                            {{ order.symbol }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                                            <span
                                                :class="order.side === 'buy' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                            >
                                                {{ order.side.toUpperCase() }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-white">
                                            ${{ parseFloat(order.price).toFixed(2) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-white">
                                            {{ parseFloat(order.amount).toFixed(4) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-white">
                                            ${{ (parseFloat(order.price) * parseFloat(order.amount)).toFixed(2) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                                            <span
                                                :class="statusColors[order.status]"
                                                class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                            >
                                                {{ statusLabels[order.status] }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                                            <button
                                                v-if="order.status === 1"
                                                @click="cancelOrder(order.id)"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                Cancel
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredOrders.length === 0">
                                        <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                                            No orders found
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Orderbook -->
                <div class="rounded-md bg-white p-6 shadow-md dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Orderbook</h2>
                        <select
                            v-model="selectedSymbol"
                            @change="watchSymbol"
                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
                        >
                            <option value="BTC">BTC</option>
                            <option value="ETH">ETH</option>
                        </select>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Side
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Price
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                <tr v-for="order in orderbook" :key="order.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        <span
                                            :class="order.side === 'buy' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                        >
                                            {{ order.side.toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-white">
                                        ${{ parseFloat(order.price).toFixed(2) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900 dark:text-white">
                                        {{ parseFloat(order.amount).toFixed(2) }}
                                    </td>
                                </tr>
                                <tr v-if="orderbook.length === 0">
                                    <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">
                                        No open orders
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
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
