<template>
    <div
        class="flex min-h-screen flex-col items-center bg-blue-50/40 py-12 sm:justify-center sm:py-0 dark:bg-[#0f172a]"
    >
        <div
            class="my-6 w-full overflow-hidden bg-white px-6 py-5 shadow-xl border border-blue-100/50 sm:max-w-md sm:rounded-2xl dark:border-slate-800/80 dark:bg-[#1e293b]"
        >
            <slot />
        </div>
    </div>
</template>
