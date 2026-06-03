<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: null });

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/force-change-password', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const cancel = () => {
    router.post('/logout');
};
</script>

<template>
    <Head title="Ganti Password — Dashboard RSUD Tarakan" />

    <!-- Full screen overlay backdrop -->
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm">
        <!-- Modal Card -->
        <div class="mx-4 w-full max-w-md animate-in fade-in zoom-in duration-300">
            <div class="relative rounded-2xl bg-white p-8 shadow-2xl">
                <!-- Close / Cancel Button -->
                <button
                    type="button"
                    class="absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                    @click="cancel"
                    title="Batal, kembali ke login"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" x2="6" y1="6" y2="18" />
                        <line x1="6" x2="18" y1="6" y2="18" />
                    </svg>
                </button>

                <!-- Warning Icon -->
                <div class="mb-6 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                            <line x1="12" x2="12" y1="9" y2="13" />
                            <line x1="12" x2="12.01" y1="17" y2="17" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Ganti Password</h2>
                    <p class="mt-2 text-sm text-gray-500">
                        Anda masih menggunakan password default. Demi keamanan, silakan ganti password Anda sebelum melanjutkan.
                    </p>
                </div>

                <!-- Info Box -->
                <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" x2="12" y1="8" y2="12" />
                            <line x1="12" x2="12.01" y1="16" y2="16" />
                        </svg>
                        <p class="text-xs text-amber-700">
                            Password baru minimal 8 karakter dan tidak boleh sama dengan password default.
                        </p>
                    </div>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- New Password -->
                    <div class="space-y-2">
                        <Label for="password" class="text-sm font-medium text-gray-700">Password Baru</Label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                                    <path d="M7 11V7a5 5 0 0110 0v4" />
                                </svg>
                            </div>
                            <Input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autofocus
                                autocomplete="new-password"
                                placeholder="Masukkan password baru"
                                class="h-12 pl-11 pr-11 text-sm"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 transition-colors hover:text-gray-600"
                                @click="showPassword = !showPassword"
                            >
                                <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                    <line x1="2" x2="22" y1="2" y2="22" />
                                </svg>
                            </button>
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="space-y-2">
                        <Label for="password_confirmation" class="text-sm font-medium text-gray-700">Konfirmasi Password Baru</Label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 11l3 3L22 4" />
                                    <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                                </svg>
                            </div>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showConfirmPassword ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                placeholder="Ulangi password baru"
                                class="h-12 pl-11 pr-11 text-sm"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 transition-colors hover:text-gray-600"
                                @click="showConfirmPassword = !showConfirmPassword"
                            >
                                <svg v-if="!showConfirmPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                    <line x1="2" x2="22" y1="2" y2="22" />
                                </svg>
                            </button>
                        </div>
                        <InputError :message="form.errors.password_confirmation" />
                    </div>

                    <!-- Submit Button -->
                    <Button
                        type="submit"
                        class="h-12 w-full bg-gradient-to-r from-blue-600 to-blue-700 text-sm font-semibold shadow-lg shadow-blue-500/25 transition-all hover:from-blue-700 hover:to-blue-800"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" class="mr-2" />
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Password Baru' }}
                    </Button>
                </form>
            </div>
        </div>
    </div>
</template>
