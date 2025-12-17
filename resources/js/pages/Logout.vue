<script setup lang="ts">
import { logout } from '@/lib/api';
import { Head, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';

onMounted(() => {
    logout();
    router.visit('/login');
});
</script>

<template>
    <Head title="Logging out..." />
    <div class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-900">
        <div class="text-center">
            <p class="text-lg text-gray-900 dark:text-white">Logging out...</p>
        </div>
    </div>
</template>

