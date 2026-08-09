<script setup>
import { ref, watch } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    captchaImages: {
        type: Array,
        default: () => [],
    },
});

const currentImageIndex = ref(0);
const captchaPool = ref([]);
const currentCaptcha = ref('');

watch(
    () => props.captchaImages,
    (newImages) => {
        captchaPool.value = [...newImages];
        currentImageIndex.value = 0;
        currentCaptcha.value = captchaPool.value[0] || '';
    },
    { immediate: true }
);

const refreshCaptcha = async () => {
    form.captcha = '';
    if (captchaPool.value.length > 0) {
        currentImageIndex.value = (currentImageIndex.value + 1) % captchaPool.value.length;
        currentCaptcha.value = captchaPool.value[currentImageIndex.value];
    }
    try {
        const response = await axios.get(route('captcha.replenish'));
        if (response.data.image) {
            captchaPool.value.push(response.data.image);
        }
    } catch (error) {
        console.error('Failed to replenish CAPTCHA pool:', error);
    }
};

const form = useForm({
    email: '',
    password: '',
    remember: false,
    captcha: '',
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password', 'captcha');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <!-- Tabs Header -->
        <div class="flex border-b border-blue-100/30 dark:border-slate-800/80 mb-6">
            <Link
                :href="route('login')"
                class="w-1/2 text-center pb-3 text-sm font-semibold border-b-2 transition-all outline-none"
                :class="route().current('login') 
                    ? 'border-b-2 border-blue-600 text-blue-600 dark:border-blue-500 dark:text-blue-500' 
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
            >
                Log In
            </Link>
            <Link
                :href="route('register')"
                class="w-1/2 text-center pb-3 text-sm font-semibold border-b-2 transition-all outline-none"
                :class="route().current('register') 
                    ? 'border-b-2 border-blue-600 text-blue-600 dark:border-blue-500 dark:text-blue-500' 
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
            >
                Register
            </Link>
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="captcha" value="Security Code" />

                <div class="flex items-center gap-4 mt-1">
                    <img
                        :src="currentCaptcha"
                        alt="CAPTCHA"
                        class="rounded border border-gray-300 dark:border-slate-800/80 bg-white"
                        style="height: 40px; width: 120px;"
                    />
                    <button
                        type="button"
                        @click="refreshCaptcha"
                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline focus:outline-none"
                    >
                        Refresh Code
                    </button>
                </div>

                <TextInput
                    id="captcha"
                    type="text"
                    class="mt-2 block w-full"
                    v-model="form.captcha"
                    required
                    placeholder="Enter code shown above"
                    autocomplete="off"
                />

                <InputError class="mt-2" :message="form.errors.captcha" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400"
                        >Remember me</span
                    >
                </label>
            </div>

            <div class="mt-6 flex items-center justify-between text-sm">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:underline outline-none font-medium"
                >
                    Forgot password?
                </Link>
                <div v-else></div>

                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Log in
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
