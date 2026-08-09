<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    stats: Object,
    syncLogs: Object,
    filters: Object,
});

const perPage = ref(parseInt(props.filters?.per_page) || 5);
const statusFilter = ref(props.filters?.status || '');
const sortField = ref(props.filters?.sort_field || 'started_at');
const sortOrder = ref(props.filters?.sort_order || 'desc');

const toggleSort = (field) => {
    let order = 'asc';
    if (sortField.value === field && sortOrder.value === 'asc') {
        order = 'desc';
    }
    sortField.value = field;
    sortOrder.value = order;
};

watch(
    [perPage, statusFilter, sortField, sortOrder],
    ([newPerPage, newStatus, newSortField, newSortOrder]) => {
        router.get(
            route('dashboard'),
            { 
                per_page: newPerPage, 
                status: newStatus,
                sort_field: newSortField,
                sort_order: newSortOrder
            },
            { preserveState: true, replace: true }
        );
    }
);

const isSyncing = ref(false);
const syncProgress = ref({
    id: null,
    status: 'pending',
    total_records: 0,
    processed_records: 0,
    failed_records: 0,
    error_message: null,
    started_at: null,
    completed_at: null,
    failures_log: [],
});

const showDetailsModal = ref(false);
const selectedLog = ref(null);

const viewLogDetails = (log) => {
    selectedLog.value = log;
    showDetailsModal.value = true;
};

const closeDetailsModal = () => {
    showDetailsModal.value = false;
    selectedLog.value = null;
};

const progressWidth = computed(() => {
    if (!syncProgress.value.total_records) {
        return '0%';
    }
    const totalAttempted = (syncProgress.value.processed_records || 0) + (syncProgress.value.failed_records || 0);
    const percent = (totalAttempted / syncProgress.value.total_records) * 100;
    return `${Math.min(100, Math.max(0, percent))}%`;
});

const progressPercent = computed(() => {
    if (!syncProgress.value.total_records) return 0;
    const totalAttempted = (syncProgress.value.processed_records || 0) + (syncProgress.value.failed_records || 0);
    const percent = (totalAttempted / syncProgress.value.total_records) * 100;
    return Math.min(100, Math.max(0, Math.round(percent)));
});

let pollInterval = null;

// Formatter for date times
const formatDateTime = (dateString) => {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    return date.toLocaleString();
};

const triggerSync = async () => {
    if (isSyncing.value) return;
    
    isSyncing.value = true;
    syncProgress.value.status = 'running';
    syncProgress.value.processed_records = 0;
    syncProgress.value.failed_records = 0;
    syncProgress.value.total_records = 0;
    syncProgress.value.error_message = null;

    try {
        const response = await axios.post(route('sync.trigger'));
        const activeLog = response.data.log;
        syncProgress.value = activeLog;
        startPolling(activeLog.id);
    } catch (error) {
        isSyncing.value = false;
        syncProgress.value.status = 'failed';
        syncProgress.value.error_message = error.response?.data?.message || 'Failed to trigger synchronisation.';
    }
};

const startPolling = (logId) => {
    if (pollInterval) clearInterval(pollInterval);
    
    pollInterval = setInterval(async () => {
        try {
            const response = await axios.get(route('sync.status', logId));
            const log = response.data;
            
            syncProgress.value = log;

            if (log.status !== 'running') {
                stopPolling();
                // Refresh dashboard stats
                router.reload({ only: ['stats', 'syncLogs'] });
            }
        } catch (error) {
            console.error('Error polling sync status:', error);
        }
    }, 1500);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
    isSyncing.value = false;
};

// Check on mount if there is an active sync running
onMounted(async () => {
    try {
        const response = await axios.get(route('sync.latest-status'));
        const latest = response.data;
        if (latest && latest.status === 'running') {
            isSyncing.value = true;
            syncProgress.value = latest;
            startPolling(latest.id);
        }
    } catch (error) {
        console.error('Error fetching latest sync status:', error);
    }
});

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-100">
                    Dashboard
                </h2>
                <button
                    @click="triggerSync"
                    :disabled="isSyncing"
                    class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg shadow-sm hover:shadow transition focus:outline-none disabled:opacity-50 w-full sm:w-auto"
                >
                    {{ isSyncing ? 'Syncing...' : 'Sync Customers' }}
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- Sync Progress Card (Only visible when active or recently failed) -->
                <div v-if="isSyncing || syncProgress.status === 'failed' || syncProgress.status === 'success'" 
                     class="overflow-hidden bg-white shadow-xl sm:rounded-xl border border-gray-100 dark:border-slate-800/80 dark:bg-[#111c30] transition-all duration-300">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex flex-wrap items-center gap-y-1.5">
                            <span class="relative flex h-3 w-3 mr-2.5" v-if="syncProgress.status === 'running'">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
                            </span>
                            Synchronisation Status: 
                            <span class="ml-1.5 capitalize font-bold" 
                                  :class="{
                                      'text-blue-600 dark:text-blue-400': syncProgress.status === 'running',
                                      'text-green-600 dark:text-green-400': syncProgress.status === 'success',
                                      'text-red-600 dark:text-red-400': syncProgress.status === 'failed'
                                  }">
                                {{ syncProgress.status }}
                            </span>
                        </h3>

                        <!-- Progress Bar Container -->
                        <div>
                            <div class="flex flex-col sm:flex-row sm:justify-between text-sm font-medium text-gray-600 dark:text-gray-400 mb-2 gap-y-1">
                                <span v-if="syncProgress.status === 'running'">Importing records...</span>
                                <span v-else class="text-gray-600 dark:text-gray-400 font-semibold break-words">
                                    {{ syncProgress.processed_records || 0 }} {{ (syncProgress.processed_records || 0) === 1 ? 'customer' : 'customers' }} successfully synced, {{ syncProgress.failed_records || 0 }} failed to sync
                                </span>
                                
                                <span>{{ (syncProgress.processed_records || 0) + (syncProgress.failed_records || 0) }} / {{ syncProgress.total_records || '?' }} ({{ progressPercent }}%)</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700/60 rounded-full h-3.5 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-300 bg-gradient-to-r from-blue-500 to-blue-600"
                                     :style="{ width: progressWidth }"></div>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-3 text-xs text-gray-500 dark:text-gray-400 gap-3">
                                <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                                    <span>Processed: <strong class="text-gray-900 dark:text-gray-100 font-semibold">{{ (syncProgress.processed_records || 0) + (syncProgress.failed_records || 0) }}</strong></span>
                                    <span>Synced: <strong class="text-green-650 dark:text-green-450 font-semibold">{{ syncProgress.processed_records || 0 }}</strong></span>
                                    <span>Failed: <strong class="text-red-600 font-semibold">{{ syncProgress.failed_records || 0 }}</strong></span>
                                </div>
                                <button 
                                    v-if="syncProgress.status !== 'running' && syncProgress.failed_records > 0"
                                    type="button"
                                    @click="viewLogDetails(syncProgress)"
                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 underline font-medium text-left sm:text-right"
                                >
                                    View details
                                </button>
                            </div>
                        </div>

                        <!-- Failure Info Box -->
                        <div v-if="syncProgress.status === 'failed'" class="mt-4 p-4 bg-red-50 border-l-4 border-red-500 dark:bg-red-950/20 dark:border-red-600/50 rounded-r-lg">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                        {{ syncProgress.error_message || 'An unknown error occurred during sync.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards Grid -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Total Customers -->
                    <div class="overflow-hidden bg-white shadow sm:rounded-xl border border-gray-100 dark:border-slate-800/80 dark:bg-[#111c30] hover:scale-[1.02] transition-transform duration-200">
                        <div class="p-5 flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Customers</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ stats.totalCustomers }}</p>
                            </div>
                            <div class="p-3 bg-blue-50 dark:bg-blue-900 rounded-lg">
                                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Active Customers -->
                    <div class="overflow-hidden bg-white shadow sm:rounded-xl border border-gray-100 dark:border-slate-800/80 dark:bg-[#111c30] hover:scale-[1.02] transition-transform duration-200">
                        <div class="p-5 flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active Customers</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ stats.activeCustomers }}</p>
                            </div>
                            <div class="p-3 bg-green-50 dark:bg-green-900 rounded-lg">
                                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Failed Records -->
                    <div class="overflow-hidden bg-white shadow sm:rounded-xl border border-gray-100 dark:border-slate-800/80 dark:bg-[#111c30] hover:scale-[1.02] transition-transform duration-200">
                        <div class="p-5 flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Failed Records</p>
                                <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ stats.failedRecords }}</p>
                            </div>
                            <div class="p-3 bg-red-50 dark:bg-red-900 rounded-lg">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Last Sync Time -->
                    <div class="overflow-hidden bg-white shadow sm:rounded-xl border border-gray-100 dark:border-slate-800/80 dark:bg-[#111c30] hover:scale-[1.02] transition-transform duration-200">
                        <div class="p-5 flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Sync Time</p>
                                <p class="text-md font-bold text-gray-900 dark:text-gray-100 mt-2 truncate max-w-[170px]">{{ formatDateTime(stats.lastSyncTime) }}</p>
                            </div>
                            <div class="p-3 bg-blue-50 dark:bg-blue-900 rounded-lg">
                                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Sync Logs -->
                <div class="bg-white shadow sm:rounded-xl border border-gray-100 dark:border-slate-800/80 dark:bg-[#111c30]">
                    <div class="p-6 border-b border-gray-100 dark:border-slate-800/80 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            Sync Logs
                        </h3>
                        <div>
                            <select
                                v-model="statusFilter"
                                class="block w-40 px-3 py-2 border border-gray-300 dark:border-slate-700/60 rounded-lg bg-blue-50/30 dark:bg-[#0b121f] text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm transition"
                            >
                                <option value="">All Statuses</option>
                                <option value="success">Success</option>
                                <option value="failed">Failed</option>
                                <option value="running">Running</option>
                            </select>
                        </div>
                    </div>
                    <!-- Desktop Table View -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-[#0b121f]/80 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100 dark:border-slate-800/80">
                                    <th class="px-6 py-3.5 cursor-pointer hover:bg-blue-50/20 dark:hover:bg-slate-800/40 select-none" @click="toggleSort('started_at')">
                                        <div class="flex items-center space-x-1.5">
                                            <span>Started At</span>
                                            <span v-if="sortField === 'started_at'" class="text-blue-600 dark:text-blue-400 font-extrabold text-[10px] bg-blue-50 dark:bg-blue-900/40 px-1 py-0.5 rounded border border-blue-200 dark:border-blue-800/60 shadow-sm leading-none select-none">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                                            <span v-else class="text-gray-300 dark:text-slate-650 text-[10px]">⇅</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3.5 cursor-pointer hover:bg-blue-50/20 dark:hover:bg-slate-800/40 select-none" @click="toggleSort('completed_at')">
                                        <div class="flex items-center space-x-1.5">
                                            <span>Completed At</span>
                                            <span v-if="sortField === 'completed_at'" class="text-blue-600 dark:text-blue-400 font-extrabold text-[10px] bg-blue-50 dark:bg-blue-900/40 px-1 py-0.5 rounded border border-blue-200 dark:border-blue-800/60 shadow-sm leading-none select-none">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                                            <span v-else class="text-gray-300 dark:text-slate-650 text-[10px]">⇅</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3.5 text-center cursor-pointer hover:bg-blue-50/20 dark:hover:bg-slate-800/40 select-none" @click="toggleSort('status')">
                                        <div class="flex items-center justify-center space-x-1.5">
                                            <span>Status</span>
                                            <span v-if="sortField === 'status'" class="text-blue-600 dark:text-blue-400 font-extrabold text-[10px] bg-blue-50 dark:bg-blue-900/40 px-1 py-0.5 rounded border border-blue-200 dark:border-blue-800/60 shadow-sm leading-none select-none">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                                            <span v-else class="text-gray-300 dark:text-slate-650 text-[10px]">⇅</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3.5 text-center cursor-pointer hover:bg-blue-50/20 dark:hover:bg-slate-800/40 select-none" @click="toggleSort('total_records')">
                                        <div class="flex items-center justify-center space-x-1.5">
                                            <span>Total</span>
                                            <span v-if="sortField === 'total_records'" class="text-blue-600 dark:text-blue-400 font-extrabold text-[10px] bg-blue-50 dark:bg-blue-900/40 px-1 py-0.5 rounded border border-blue-200 dark:border-blue-800/60 shadow-sm leading-none select-none">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                                            <span v-else class="text-gray-300 dark:text-slate-650 text-[10px]">⇅</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3.5 text-center cursor-pointer hover:bg-blue-50/20 dark:hover:bg-slate-800/40 select-none" @click="toggleSort('processed_records')">
                                        <div class="flex items-center justify-center space-x-1.5">
                                            <span>Success</span>
                                            <span v-if="sortField === 'processed_records'" class="text-blue-600 dark:text-blue-400 font-extrabold text-[10px] bg-blue-50 dark:bg-blue-900/40 px-1 py-0.5 rounded border border-blue-200 dark:border-blue-800/60 shadow-sm leading-none select-none">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                                            <span v-else class="text-gray-300 dark:text-slate-650 text-[10px]">⇅</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-3.5 text-center cursor-pointer hover:bg-blue-50/20 dark:hover:bg-slate-800/40 select-none" @click="toggleSort('failed_records')">
                                        <div class="flex items-center justify-center space-x-1.5">
                                            <span>Failed</span>
                                            <span v-if="sortField === 'failed_records'" class="text-blue-600 dark:text-blue-400 font-extrabold text-[10px] bg-blue-50 dark:bg-blue-900/40 px-1 py-0.5 rounded border border-blue-200 dark:border-blue-800/60 shadow-sm leading-none select-none">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                                            <span v-else class="text-gray-300 dark:text-slate-650 text-[10px]">⇅</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm text-gray-600 dark:text-gray-300">
                                <tr v-for="log in syncLogs.data" :key="log.id" class="hover:bg-blue-50/30 dark:hover:bg-gray-900/50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ formatDateTime(log.started_at) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ formatDateTime(log.completed_at) }}</td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <div class="flex flex-col items-center justify-center space-y-1">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wide"
                                                  :class="{
                                                      'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': log.status === 'success',
                                                      'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': log.status === 'failed',
                                                      'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': log.status === 'running'
                                                  }">
                                                {{ log.status }}
                                            </span>
                                            <button 
                                                v-if="log.status === 'failed' && log.error_message" 
                                                @click="viewLogDetails(log)"
                                                class="text-[10px] text-red-650 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 underline font-medium"
                                            >
                                                Details
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">{{ log.total_records }}</td>
                                    <td class="px-6 py-4 text-center text-green-600 dark:text-green-400 font-medium">{{ log.processed_records }}</td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <div class="flex flex-col items-center justify-center space-y-0.5">
                                            <span class="text-red-600 dark:text-red-400 font-medium">{{ log.failed_records }}</span>
                                            <button 
                                                v-if="log.failed_records > 0" 
                                                @click="viewLogDetails(log)"
                                                class="text-[10px] text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 underline font-medium"
                                            >
                                                View details
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="syncLogs.data.length === 0">
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-400 dark:text-gray-500">
                                        No synchronisation logs found. Click "Sync Customers" to initiate.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Stacked Card View -->
                    <div class="block sm:hidden divide-y divide-gray-100 dark:divide-slate-800/80">
                        <div v-for="log in syncLogs.data" :key="log.id" class="p-5 space-y-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="block text-[11px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Started At</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ formatDateTime(log.started_at) }}</span>
                                </div>
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                      :class="{
                                          'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': log.status === 'success',
                                          'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': log.status === 'failed',
                                          'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': log.status === 'running'
                                      }">
                                    {{ log.status }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="block font-medium text-gray-400 dark:text-slate-500">Completed At</span>
                                    <span class="text-gray-800 dark:text-slate-300 font-medium">{{ formatDateTime(log.completed_at) || '-' }}</span>
                                </div>
                                <div>
                                    <span class="block font-medium text-gray-400 dark:text-slate-500">Total Records</span>
                                    <span class="text-gray-800 dark:text-slate-300 font-bold">{{ log.total_records }}</span>
                                </div>
                                <div>
                                    <span class="block font-medium text-gray-400 dark:text-slate-500">Success</span>
                                    <span class="text-green-600 dark:text-green-400 font-bold">{{ log.processed_records }}</span>
                                </div>
                                <div>
                                    <span class="block font-medium text-gray-400 dark:text-slate-500">Failed</span>
                                    <span class="text-red-600 dark:text-red-400 font-bold">{{ log.failed_records }}</span>
                                </div>
                            </div>

                            <div v-if="(log.status === 'failed' && log.error_message) || log.failed_records > 0" class="pt-3 border-t border-gray-100 dark:border-slate-800/80">
                                <button 
                                    @click="viewLogDetails(log)"
                                    class="w-full text-center py-2 bg-blue-50 hover:bg-blue-100 dark:bg-slate-800/60 dark:hover:bg-slate-800 text-blue-600 dark:text-blue-400 rounded-lg text-xs font-semibold transition"
                                >
                                    {{ log.status === 'failed' ? 'View Execution Errors' : 'View Failed Records' }}
                                </button>
                            </div>
                        </div>
                        <div v-if="syncLogs.data.length === 0" class="p-8 text-center text-gray-400 dark:text-gray-500">
                            No synchronisation logs found. Click "Sync Customers" to initiate.
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="syncLogs.total > 0" class="px-6 py-4 border-t border-gray-100 dark:border-slate-800/80 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Showing {{ syncLogs.from || 0 }} to {{ syncLogs.to || 0 }} of {{ syncLogs.total }} sync runs
                            </div>
                            <div class="flex items-center space-x-2">
                                <label for="sync_logs_per_page" class="text-xs text-gray-500 dark:text-gray-400">Per page:</label>
                                <select
                                    id="sync_logs_per_page"
                                    v-model="perPage"
                                    class="block pl-2.5 pr-8 py-1.5 border border-gray-300 dark:border-slate-700/60 rounded-lg bg-white dark:bg-[#0b121f] text-gray-900 dark:text-gray-100 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    <option :value="5">5</option>
                                    <option :value="10">10</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="syncLogs.links && syncLogs.links.length > 3" class="flex flex-wrap items-center justify-center gap-1.5">
                            <template v-for="link in syncLogs.links" :key="link.label">
                                <button
                                    v-if="link.url"
                                    @click="router.visit(link.url)"
                                    class="px-3 py-1.5 rounded border text-xs font-medium transition"
                                    :class="link.active 
                                        ? 'bg-blue-600 border-blue-600 text-white' 
                                        : 'bg-white dark:bg-[#111c30] border-gray-300 dark:border-slate-700/60 text-gray-700 dark:text-gray-300 hover:bg-blue-50/30 dark:hover:bg-gray-700'"
                                    v-html="link.label"
                                />
                                <span v-else class="px-3 py-1.5 border border-gray-200 dark:border-slate-800/80 rounded text-xs text-gray-400 cursor-not-allowed" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Sync Details Modal -->
        <Modal :show="showDetailsModal" @close="closeDetailsModal" maxWidth="2xl">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-between items-center border-b border-gray-100 dark:border-slate-800/80 pb-4">
                    <h2 class="text-lg font-bold">
                        Sync Execution Details
                    </h2>
                    <button @click="closeDetailsModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="mt-4 space-y-4 text-sm text-gray-600 dark:text-slate-350">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-gray-50 dark:bg-[#0b121f]/50 p-4 rounded-xl">
                        <div>
                            <span class="block text-xs font-semibold text-gray-400 uppercase">Started At</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatDateTime(selectedLog?.started_at) }}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold text-gray-400 uppercase">Completed At</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatDateTime(selectedLog?.completed_at) }}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold text-gray-400 uppercase">Status</span>
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wide"
                                  :class="{
                                      'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': selectedLog?.status === 'success',
                                      'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': selectedLog?.status === 'failed',
                                      'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': selectedLog?.status === 'running'
                                  }">
                                {{ selectedLog?.status }}
                            </span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold text-gray-400 uppercase mb-1">Record Counts</span>
                            <div class="grid grid-cols-3 gap-2 text-center bg-white dark:bg-[#111c30] p-2 rounded-lg border border-gray-100 dark:border-slate-800/80">
                                <div>
                                    <span class="block text-[10px] font-semibold text-gray-400 uppercase">Total</span>
                                    <span class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ selectedLog?.total_records }}</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-semibold text-gray-400 uppercase text-green-600 dark:text-green-400">Success</span>
                                    <span class="text-sm font-bold text-green-600 dark:text-green-400">{{ selectedLog?.processed_records }}</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-semibold text-gray-400 uppercase text-red-650 dark:text-red-400">Failed</span>
                                    <span class="text-sm font-bold text-red-600 dark:text-red-400">{{ selectedLog?.failed_records }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Global Sync Failure Error -->
                    <div v-if="selectedLog?.error_message" class="bg-red-50 dark:bg-red-950/20 border border-red-100 dark:border-red-900/40 p-4 rounded-xl text-red-700 dark:text-red-400">
                        <h4 class="font-semibold text-sm mb-1">Execution Crash Error</h4>
                        <p class="font-mono text-xs whitespace-pre-wrap">{{ selectedLog.error_message }}</p>
                    </div>

                    <!-- Individual Record Failures -->
                    <div v-if="selectedLog?.failures_log && selectedLog.failures_log.length > 0">
                        <h4 class="font-bold text-gray-900 dark:text-gray-100 mb-2">
                            Failed Records ({{ selectedLog.failures_log.length }})
                        </h4>
                        <div class="max-h-60 overflow-y-auto border border-gray-100 dark:border-slate-800/80 rounded-xl divide-y divide-gray-100 dark:divide-slate-800/80">
                            <div v-for="(failure, idx) in selectedLog.failures_log" :key="idx" class="p-3 bg-white dark:bg-[#111c30]">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ failure.name || 'Unknown' }}</span>
                                        <span class="text-xs text-gray-400 dark:text-slate-500 ml-2">({{ failure.email }})</span>
                                    </div>
                                    <span class="text-[10px] font-mono bg-gray-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-gray-500 dark:text-slate-400">ID: {{ failure.external_id }}</span>
                                </div>
                                <p class="text-xs text-red-600 dark:text-red-400 mt-1 font-mono">
                                    {{ failure.reason }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        @click="closeDetailsModal"
                        class="px-4 py-2 border border-gray-300 dark:border-slate-700/60 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#111c30] hover:bg-blue-50/30 dark:hover:bg-gray-700 transition"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
