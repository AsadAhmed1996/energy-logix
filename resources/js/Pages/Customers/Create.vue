<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    status: 'active',
    address_street: '',
    address_city: '',
    address_state: '',
    address_zip: '',
    address_country: '',
});

const countries = [
    "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda", "Argentina", "Armenia", "Australia", "Austria",
    "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bhutan",
    "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia",
    "Cameroon", "Canada", "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros", "Congo", "Costa Rica",
    "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador",
    "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji", "Finland", "France",
    "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Greece", "Grenada", "Guatemala", "Guinea", "Guinea-Bissau",
    "Guyana", "Haiti", "Honduras", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland",
    "Israel", "Italy", "Ivory Coast", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, North",
    "Korea, South", "Kosovo", "Kuwait", "Kyrgyzstan", "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya",
    "Liechtenstein", "Lithuania", "Luxembourg", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands",
    "Mauritania", "Mauritius", "Mexico", "Micronesia", "Moldova", "Monaco", "Mongolia", "Montenegro", "Morocco", "Mozambique",
    "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria", "North Macedonia",
    "Norway", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Poland",
    "Portugal", "Qatar", "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino",
    "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", "Solomon Islands",
    "Somalia", "South Africa", "South Sudan", "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria",
    "Taiwan", "Tajikistan", "Tanzania", "Thailand", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan",
    "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City",
    "Venezuela", "Vietnam", "Yemen", "Zambia", "Zimbabwe"
];

// Address Auto-Lookup States
import { ref, watch } from 'vue';
const addressSuggestions = ref([]);
const isSearchingAddress = ref(false);
let lookupTimeout = null;
let isSelectingSuggestion = false;

const lookupAddress = async (query) => {
    if (!query || query.length < 3) {
        addressSuggestions.value = [];
        return;
    }
    isSearchingAddress.value = true;
    try {
        const response = await fetch(`/address-lookup?q=${encodeURIComponent(query)}`);
        if (response.ok) {
            addressSuggestions.value = await response.json();
        }
    } catch (e) {
        console.error(e);
    } finally {
        isSearchingAddress.value = false;
    }
};

watch(() => form.address_street, (newVal) => {
    if (isSelectingSuggestion) {
        isSelectingSuggestion = false;
        return;
    }
    clearTimeout(lookupTimeout);
    if (!newVal) {
        addressSuggestions.value = [];
        return;
    }
    lookupTimeout = setTimeout(() => {
        lookupAddress(newVal);
    }, 400);
});

const selectSuggestion = (suggestion) => {
    const addr = suggestion.address || {};
    
    // Parse street
    let street = '';
    if (addr.house_number) street += addr.house_number + ' ';
    if (addr.road) {
        street += addr.road;
    } else if (suggestion.display_name) {
        street = suggestion.display_name.split(',')[0];
    }
    
    isSelectingSuggestion = true;
    form.address_street = street.trim();
    
    // Parse city
    form.address_city = addr.city || addr.town || addr.village || addr.suburb || '';
    
    // Parse state
    form.address_state = addr.state || addr.county || '';
    
    // Parse zip
    form.address_zip = addr.postcode || '';
    
    // Parse country
    const countryName = addr.country || '';
    const matched = countries.find(c => c.toLowerCase() === countryName.toLowerCase());
    form.address_country = matched || countryName;
    
    // Clear suggestions
    addressSuggestions.value = [];
};

const submit = () => {
    form.post(route('customers.store'));
};
</script>

<template>
    <Head title="Add Customer" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Add New Customer
                </h2>
                <Link
                    :href="route('customers.index')"
                    class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-slate-700/60 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#111c30] hover:bg-blue-50/30 dark:hover:bg-gray-700 transition w-full sm:w-auto"
                >
                    Back to Customers
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-[#111c30]">
                    <header class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Customer Details</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Please enter the contact details and status for the new customer record.</p>
                    </header>

                    <form @submit.prevent="submit" class="mt-6 space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <InputLabel for="first_name" value="First Name *" />
                                <TextInput
                                    id="first_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.first_name"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.first_name" />
                            </div>

                            <div>
                                <InputLabel for="last_name" value="Last Name *" />
                                <TextInput
                                    id="last_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.last_name"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.last_name" />
                            </div>

                            <div>
                                <InputLabel for="email" value="Email Address *" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    v-model="form.email"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div>
                                <InputLabel for="phone" value="Phone Number *" />
                                <TextInput
                                    id="phone"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.phone"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.phone" />
                            </div>

                            <div class="relative">
                                <InputLabel for="address_street" value="Street *" />
                                <div class="relative mt-1">
                                    <TextInput
                                        id="address_street"
                                        type="text"
                                        class="block w-full pr-10"
                                        placeholder="Start typing street..."
                                        v-model="form.address_street"
                                        required
                                    />
                                    <div v-if="isSearchingAddress" class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div v-if="addressSuggestions.length > 0" class="absolute z-50 left-0 right-0 mt-1.5 bg-white dark:bg-[#111c30] border border-gray-200 dark:border-slate-800/80 rounded-lg shadow-lg max-h-60 overflow-y-auto divide-y divide-gray-100 dark:divide-slate-800/80">
                                    <button
                                        v-for="suggestion in addressSuggestions"
                                        :key="suggestion.place_id"
                                        type="button"
                                        @click="selectSuggestion(suggestion)"
                                        class="w-full text-left px-4 py-3 text-sm text-gray-700 dark:text-gray-200 hover:bg-blue-50/50 dark:hover:bg-slate-800/40 transition duration-150"
                                    >
                                        {{ suggestion.display_name }}
                                    </button>
                                </div>
                                <InputError class="mt-2" :message="form.errors.address_street" />
                            </div>

                            <div>
                                <InputLabel for="address_city" value="City *" />
                                <TextInput
                                    id="address_city"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.address_city"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.address_city" />
                            </div>

                            <div>
                                <InputLabel for="address_state" value="State / Region *" />
                                <TextInput
                                    id="address_state"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.address_state"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.address_state" />
                            </div>

                            <div>
                                <InputLabel for="address_zip" value="Postal / Zip Code *" />
                                <TextInput
                                    id="address_zip"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.address_zip"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.address_zip" />
                            </div>

                            <div>
                                <InputLabel for="address_country" value="Country *" />
                                <select
                                    id="address_country"
                                    v-model="form.address_country"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-800/80 dark:bg-[#0b121f] dark:text-gray-300 dark:focus:border-blue-600 dark:focus:ring-blue-600"
                                    required
                                >
                                    <option value="" disabled>Select a Country</option>
                                    <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
                                    <option v-if="form.address_country && !countries.includes(form.address_country)" :value="form.address_country">
                                        {{ form.address_country }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.address_country" />
                            </div>

                            <div>
                                <InputLabel for="status" value="Status *" />
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-800/80 dark:bg-[#0b121f] dark:text-gray-300 dark:focus:border-blue-600 dark:focus:ring-blue-600"
                                    required
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.status" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4 pt-6 border-t border-gray-100 dark:border-slate-800/80">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg shadow-sm hover:shadow transition focus:outline-none disabled:opacity-50"
                            >
                                Create Customer
                            </button>
                            <Link
                                :href="route('customers.index')"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-700/60 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-[#111c30] hover:bg-blue-50/30 dark:hover:bg-gray-700 transition"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
