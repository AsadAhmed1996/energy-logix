<script setup>
import { ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    status: {
        type: String,
    },
    captchaImages: {
        type: Array,
        default: () => [],
    },
});

const currentImageIndex = ref(0);
const captchaPool = ref([...props.captchaImages]);
const currentCaptcha = ref(captchaPool.value[0] || '');

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
    captcha: '',
});

const submit = () => {
    form.post(route('password.email'), {
        onFinish: () => {
            form.reset('captcha');
            refreshCaptcha();
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            Forgot your password? No problem. Just let us know your email
            address and we will email you a password reset link that will allow
            you to choose a new one.
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600 dark:text-green-400"
        >
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

            <div class="mt-4 flex items-center justify-between text-sm">
                <Link
                    :href="route('login')"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:underline outline-none font-medium"
                >
                    Back to Log In
                </Link>

                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Email Password Reset Link
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
