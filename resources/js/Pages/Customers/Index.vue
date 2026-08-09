<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

// Custom debounce implementation
const debounce = (fn, delay) => {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

const props = defineProps({
    customers: Object,
    filters: Object,
});

// Search and Filter states
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const perPage = ref(parseInt(props.filters.per_page) || 10);
const sortField = ref(props.filters.sort_field || 'created_at');
const sortOrder = ref(props.filters.sort_order || 'desc');

const toggleSort = (field) => {
    let order = 'asc';
    if (sortField.value === field && sortOrder.value === 'asc') {
        order = 'desc';
    }
    sortField.value = field;
    sortOrder.value = order;
};

// Watch search, status, perPage, sortField, and sortOrder to trigger filtering with debounce
watch(
    [search, status, perPage, sortField, sortOrder],
    debounce(([newSearch, newStatus, newPerPage, newSortField, newSortOrder]) => {
        router.get(
            route('customers.index'),
            { 
                search: newSearch, 
                status: newStatus, 
                per_page: newPerPage,
                sort_field: newSortField,
                sort_order: newSortOrder
            },
            { preserveState: true, replace: true }
        );
    }, 300)
);

import Modal from '@/Components/Modal.vue';

const showDeleteModal = ref(false);
const customerToDelete = ref(null);

const confirmDeleteCustomer = (customer) => {
    customerToDelete.value = customer;
    showDeleteModal.value = true;
};

const cancelDelete = () => {
    showDeleteModal.value = false;
    customerToDelete.value = null;
};

const executeDelete = () => {
    if (customerToDelete.value) {
        router.delete(route('customers.destroy', customerToDelete.value.id), {
            onFinish: () => {
                showDeleteModal.value = false;
                customerToDelete.value = null;
            }
        });
    }
};

// Sync Operation (triggered directly from the list as well)
const isSyncing = ref(false);
const triggerSync = async () => {
    if (isSyncing.value) return;
    isSyncing.value = true;
    try {
        await axios.post(route('sync.trigger'));
        router.visit(route('dashboard')); // Redirect to dashboard to show sync progress live!
    } catch (error) {
        isSyncing.value = false;
        alert(error.response?.data?.message || 'Failed to trigger synchronization.');
    }
};
</script>

<template>
    <Head title="Customers" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-100">
                    Customers
                </h2>
                <div class="flex flex-col sm:flex-row gap-3">
                    <button
                        @click="triggerSync"
                        :disabled="isSyncing"
                        class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg shadow-sm hover:shadow transition focus:outline-none disabled:opacity-50 w-full sm:w-auto"
                    >
                        {{ isSyncing ? 'Syncing...' : 'Sync Customers' }}
                    </button>
                    <Link
                        :href="route('customers.create')"
                        class="inline-flex items-center justify-center px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm rounded-lg shadow-sm hover:shadow transition focus:outline-none w-full sm:w-auto"
                    >
                        Add Customer
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- Search & Filters -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 bg-white dark:bg-[#111c30] shadow rounded-xl border border-gray-100 dark:border-slate-800/80">
                    <div class="relative flex-1 max-w-md">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name, email, phone..."
                            class="block w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-slate-700/60 rounded-lg bg-blue-50/30 dark:bg-[#0b121f] text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm transition"
                        />
                    </div>
                    <div class="flex items-center gap-3">
                        <select
                            v-model="status"
                            class="block w-40 px-3 py-2 border border-gray-300 dark:border-slate-700/60 rounded-lg bg-blue-50/30 dark:bg-[#0b121f] text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm transition"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Customer Table & Card Grid -->
                <div class="bg-white dark:bg-[#111c30] shadow rounded-xl border border-gray-100 dark:border-slate-800/80 overflow-hidden">
                    
                    <!-- Desktop Table View -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-[#0b121f]/80 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100 dark:border-slate-800/80">
                                    <th class="px-6 py-4 cursor-pointer hover:bg-blue-50/20 dark:hover:bg-slate-800/40 select-none" @click="toggleSort('name')">
                                        <div class="flex items-center space-x-1.5">
                                            <span>Name</span>
                                            <span v-if="sortField === 'first_name' || sortField === 'name'" class="text-blue-600 dark:text-blue-400 font-extrabold text-[10px] bg-blue-50 dark:bg-blue-900/40 px-1 py-0.5 rounded border border-blue-200 dark:border-blue-800/60 shadow-sm leading-none select-none">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                                            <span v-else class="text-gray-300 dark:text-slate-650 text-[10px]">⇅</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 cursor-pointer hover:bg-blue-50/20 dark:hover:bg-slate-800/40 select-none" @click="toggleSort('email')">
                                        <div class="flex items-center space-x-1.5">
                                            <span>Email</span>
                                            <span v-if="sortField === 'email'" class="text-blue-600 dark:text-blue-400 font-extrabold text-[10px] bg-blue-50 dark:bg-blue-900/40 px-1 py-0.5 rounded border border-blue-200 dark:border-blue-800/60 shadow-sm leading-none select-none">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                                            <span v-else class="text-gray-300 dark:text-slate-650 text-[10px]">⇅</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 cursor-pointer hover:bg-blue-50/20 dark:hover:bg-slate-800/40 select-none" @click="toggleSort('phone')">
                                        <div class="flex items-center space-x-1.5">
                                            <span>Phone</span>
                                            <span v-if="sortField === 'phone'" class="text-blue-600 dark:text-blue-400 font-extrabold text-[10px] bg-blue-50 dark:bg-blue-900/40 px-1 py-0.5 rounded border border-blue-200 dark:border-blue-800/60 shadow-sm leading-none select-none">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                                            <span v-else class="text-gray-300 dark:text-slate-650 text-[10px]">⇅</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 cursor-pointer hover:bg-blue-50/20 dark:hover:bg-slate-800/40 select-none" @click="toggleSort('status')">
                                        <div class="flex items-center space-x-1.5">
                                            <span>Status</span>
                                            <span v-if="sortField === 'status'" class="text-blue-600 dark:text-blue-400 font-extrabold text-[10px] bg-blue-50 dark:bg-blue-900/40 px-1 py-0.5 rounded border border-blue-200 dark:border-blue-800/60 shadow-sm leading-none select-none">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                                            <span v-else class="text-gray-300 dark:text-slate-650 text-[10px]">⇅</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-4">Address</th>
                                    <th class="px-6 py-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm text-gray-600 dark:text-gray-300">
                                <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-blue-50/30 dark:hover:bg-gray-900/50 transition duration-150">
                                    <td class="px-6 py-4 font-semibold text-gray-900 dark:text-gray-100 whitespace-normal break-words">
                                        {{ customer.first_name }} {{ customer.last_name }}
                                        <span v-if="customer.external_id" class="ml-1.5 inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400">
                                            Synced
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-normal break-words">{{ customer.email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ customer.phone || '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider"
                                              :class="customer.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-blue-50/50 text-gray-800 dark:bg-gray-700/50 dark:text-gray-400'">
                                            {{ customer.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 max-w-xs whitespace-normal break-words" :title="customer.formatted_address">{{ customer.formatted_address || '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap space-x-3">
                                        <Link :href="route('customers.edit', customer.id)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 font-medium">Edit</Link>
                                        <button @click="confirmDeleteCustomer(customer)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 font-medium">Delete</button>
                                    </td>
                                </tr>
                                <tr v-if="customers.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                                        No customers found. Click "Add Customer" or "Sync Customers" to begin.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Stacked Card View -->
                    <div class="block sm:hidden divide-y divide-gray-100 dark:divide-slate-800/80">
                        <div v-for="customer in customers.data" :key="customer.id" class="p-5 space-y-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-gray-100 text-base">
                                        {{ customer.first_name }} {{ customer.last_name }}
                                    </h4>
                                    <span v-if="customer.external_id" class="mt-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400">
                                        Synced
                                    </span>
                                </div>
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                      :class="customer.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-blue-50/50 text-gray-800 dark:bg-gray-700/50 dark:text-gray-400'">
                                    {{ customer.status }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 gap-2.5 text-sm">
                                <div>
                                    <span class="block text-[11px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Email</span>
                                    <span class="text-gray-700 dark:text-slate-300 break-all">{{ customer.email }}</span>
                                </div>
                                <div>
                                    <span class="block text-[11px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Phone</span>
                                    <span class="text-gray-700 dark:text-slate-300">{{ customer.phone || '-' }}</span>
                                </div>
                                <div>
                                    <span class="block text-[11px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">Address</span>
                                    <span class="text-gray-700 dark:text-slate-300">{{ customer.formatted_address || '-' }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 pt-3 border-t border-gray-100 dark:border-slate-800/80">
                                <Link :href="route('customers.edit', customer.id)" class="flex-1 text-center py-2 bg-blue-50 hover:bg-blue-100 dark:bg-slate-800/60 dark:hover:bg-slate-800 text-blue-600 dark:text-blue-400 rounded-lg text-xs font-semibold transition">
                                    Edit
                                </Link>
                                <button @click="confirmDeleteCustomer(customer)" class="flex-1 text-center py-2 bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-950/40 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold transition">
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div v-if="customers.data.length === 0" class="p-8 text-center text-gray-400 dark:text-gray-500">
                            No customers found. Click "Add Customer" or "Sync Customers" to begin.
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="customers.total > 0" class="px-6 py-4 border-t border-gray-100 dark:border-slate-800/80 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Showing {{ customers.from || 0 }} to {{ customers.to || 0 }} of {{ customers.total }} customers
                            </div>
                            <div class="flex items-center space-x-2">
                                <label for="per_page" class="text-xs text-gray-500 dark:text-gray-400">Per page:</label>
                                <select
                                    id="per_page"
                                    v-model="perPage"
                                    class="block pl-2.5 pr-8 py-1.5 border border-gray-300 dark:border-slate-700/60 rounded-lg bg-white dark:bg-[#0b121f] text-gray-900 dark:text-gray-100 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                >
                                    <option :value="10">10</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                    <option :value="100">100</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="customers.links && customers.links.length > 3" class="flex flex-wrap items-center justify-center gap-1.5">
                            <template v-for="link in customers.links" :key="link.label">
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

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="cancelDelete" maxWidth="md">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                    Delete Customer
                </h2>
                <p class="mt-3 text-sm text-gray-600 dark:text-slate-400">
                    Are you sure you want to delete <span class="font-semibold text-gray-900 dark:text-gray-200">{{ customerToDelete?.first_name }} {{ customerToDelete?.last_name }}</span>?
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="cancelDelete"
                        class="px-4 py-2 border border-gray-300 dark:border-slate-700/60 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#111c30] hover:bg-blue-50/30 dark:hover:bg-gray-700 transition"
                    >
                        Cancel
                    </button>
                    <button
                        @click="executeDelete"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium text-sm rounded-lg shadow-sm hover:shadow transition focus:outline-none"
                    >
                        Delete Customer
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
