<script setup>
import MainLayout from '../Layouts/MainLayout.vue';
import {
    Building2,
    CalendarCheck2,
    CircleDollarSign,
    Clock3,
} from 'lucide-vue-next';

const stats = [
    {
        label: 'Total Vendors',
        value: '1,248',
        trend: '+8.2% vs last month',
        icon: Building2,
    },
    {
        label: 'Active Bookings',
        value: '326',
        trend: '+4.1% this week',
        icon: CalendarCheck2,
    },
    {
        label: 'Monthly Revenue',
        value: '$184,920',
        trend: '+12.7% MTD growth',
        icon: CircleDollarSign,
    },
    {
        label: 'Pending Approvals',
        value: '19',
        trend: '5 need review today',
        icon: Clock3,
    },
];

const recentBookings = [
    {
        id: 'BK-1094',
        vendor: 'Asteria Events',
        client: 'Monarch Holdings',
        date: 'May 6, 2026',
        status: 'Confirmed',
    },
    {
        id: 'BK-1093',
        vendor: 'Nexa Suites',
        client: 'Eclipse Group',
        date: 'May 6, 2026',
        status: 'Pending',
    },
    {
        id: 'BK-1092',
        vendor: 'Prime Venue Co.',
        client: 'Northline Capital',
        date: 'May 5, 2026',
        status: 'Confirmed',
    },
    {
        id: 'BK-1091',
        vendor: 'Lumen Spaces',
        client: 'Vertex Partners',
        date: 'May 5, 2026',
        status: 'Review',
    },
];
</script>

<template>
    <MainLayout>
        <div class="space-y-8">
            <header class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl">
                    Dashboard Overview
                </h1>
                <p class="text-sm text-gray-500 sm:text-base">
                    Welcome back, here's what's happening today.
                </p>
            </header>

            <section
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Dashboard statistics"
            >
                <article
                    v-for="stat in stats"
                    :key="stat.label"
                    class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">{{ stat.label }}</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ stat.value }}</p>
                        </div>
                        <span
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-gray-50 text-gray-600"
                            aria-hidden="true"
                        >
                            <component :is="stat.icon" class="h-5 w-5" />
                        </span>
                    </div>
                    <p class="mt-4 text-xs font-medium text-emerald-600">{{ stat.trend }}</p>
                </article>
            </section>

            <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Recent Bookings</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    Booking ID
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    Vendor
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    Client
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    Date
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr
                                v-for="booking in recentBookings"
                                :key="booking.id"
                                class="transition hover:bg-gray-50/80"
                            >
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ booking.id }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    {{ booking.vendor }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    {{ booking.client }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ booking.date }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700"
                                    >
                                        {{ booking.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </MainLayout>
</template>
