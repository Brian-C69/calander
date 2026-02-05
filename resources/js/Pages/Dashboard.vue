<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="p-6 text-gray-900 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-semibold text-slate-800">Welcome back</div>
                                <div class="text-sm text-slate-600">Jump into the family calendar.</div>
                            </div>
                            <div class="flex gap-3">
                                <Link :href="route('calendar.index')">
                                    <PrimaryButton>Open Calendar</PrimaryButton>
                                </Link>
                                <Link href="/">
                                    <SecondaryButton>Home</SecondaryButton>
                                </Link>
                            </div>
                        </div>
                        <div class="text-sm text-slate-500">You're logged in.</div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
