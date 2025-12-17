<script setup lang="ts">
import { setAuthToken } from '@/lib/api';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const email = ref('');
const password = ref('');
const errors = ref<Record<string, string>>({});
const loading = ref(false);

const login = async () => {
    loading.value = true;
    errors.value = {};

    try {
        const response = await fetch('/api/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                email: email.value,
                password: password.value,
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
                if (Object.keys(formattedErrors).length === 0 && data.message) {
                    errors.value = { email: data.message };
                }
            } else {
                errors.value = { email: data.message || 'An error occurred' };
            }
            loading.value = false;
            return;
        }

        setAuthToken(data.token);

        router.visit('/orders');
    } catch (error) {
        console.error(error);
        errors.value = { email: 'An error occurred. Please try again.' };
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Login" />
    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 dark:bg-gray-900 sm:px-6 lg:px-8">
        <div class="w-full max-w-md space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Sign in to your account
                </h2>
            </div>
            <form class="mt-8 space-y-6" @submit.prevent="login">
                <div class="space-y-4 rounded-md shadow-sm">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email address
                        </label>
                        <input
                            id="email"
                            v-model="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            required
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder-gray-500 focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 sm:text-sm"
                            placeholder="Enter your email"
                        />
                        <p v-if="errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ errors.email }}
                        </p>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </label>
                        <input
                            id="password"
                            v-model="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 placeholder-gray-500 focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 sm:text-sm"
                            placeholder="Enter your password"
                        />
                        <p v-if="errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ errors.password }}
                        </p>
                    </div>
                </div>

                <div>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="group relative flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-indigo-500 dark:hover:bg-indigo-600 dark:focus:ring-indigo-400"
                    >
                        <span v-if="loading">Signing in...</span>
                        <span v-else>Sign in</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

