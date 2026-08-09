<script setup>
import { ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-blue-50/20 dark:bg-[#0f172a]">
            <nav class="border-b border-[#244b7e] bg-[#1a365d]">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex items-center">
                            <!-- Mobile Navigation Toggle -->
                            <div class="flex items-center sm:hidden">
                                <button
                                    @click="showingNavigationDropdown = !showingNavigationDropdown"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-slate-300 transition duration-150 ease-in-out hover:bg-[#204374] hover:text-white focus:bg-[#204374] focus:text-white focus:outline-none dark:text-slate-350 dark:hover:bg-[#204374] dark:hover:text-white dark:focus:bg-[#204374] dark:focus:text-white"
                                >
                                    <svg
                                        class="h-6 w-6"
                                        stroke="currentColor"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            :class="{
                                                hidden: showingNavigationDropdown,
                                                'inline-flex': !showingNavigationDropdown,
                                            }"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 6h16M4 12h16M4 18h16"
                                        />
                                        <path
                                            :class="{
                                                hidden: !showingNavigationDropdown,
                                                'inline-flex': showingNavigationDropdown,
                                            }"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <!-- Desktop Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    :href="route('customers.index')"
                                    :active="route().current('customers.*')"
                                >
                                    Customers
                                </NavLink>
                            </div>
                        </div>

                        <!-- Account Dropdown remains available on every screen size -->
                        <div class="ms-3 flex h-full items-center sm:ms-6">
                            <div class="h-full">
                                <Dropdown
                                    align="right"
                                    width="48"
                                    root-classes="relative h-full flex items-center"
                                    dropdown-classes="top-full mt-0 border border-gray-100 dark:border-slate-800/80 shadow-lg"
                                >
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                aria-label="Open account menu"
                                                class="inline-flex items-center rounded-md border border-transparent bg-[#1a365d] px-3 py-2 text-sm font-medium leading-4 text-white transition duration-150 ease-in-out hover:text-slate-200 focus:outline-none dark:hover:text-slate-200"
                                            >
                                                <span class="hidden sm:inline">
                                                    {{ $page.props.auth.user.name }}
                                                </span>

                                                <svg
                                                    class="h-5 w-5 sm:hidden"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M10 2a4 4 0 100 8 4 4 0 000-8zm-7 16a7 7 0 1114 0H3z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>

                                                <svg
                                                    class="-me-0.5 ms-2 hidden h-4 w-4 sm:block"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('customers.index')"
                            :active="route().current('customers.*')"
                        >
                            Customers
                        </ResponsiveNavLink>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                v-if="$slots.header"
                class="border-b border-blue-100/30 bg-blue-50/80 shadow-sm dark:border-slate-800/80 dark:bg-[#131f33]/90"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>