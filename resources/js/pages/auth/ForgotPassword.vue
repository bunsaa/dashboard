<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: null });

const page = usePage();
const status = computed(() => page.props.status as string | undefined);

const form = useForm({ nip: '' });

const submit = () => {
    form.post('/reset-password-default', { preserveScroll: true });
};
</script>

<template>
    <Head title="Lupa Password — Dashboard RSUD Tarakan" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50/50 px-4">
        <div class="w-full max-w-md">
            <div class="rounded-2xl bg-white p-8 shadow-lg">
                <!-- Logo -->
                <div class="mb-8 text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-sky-500 shadow-lg shadow-blue-500/25">
                        <svg class="h-8 w-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0110 0v4" />
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold text-gray-800">Lupa Password</h1>
                    <p class="mt-2 text-sm text-gray-500">Masukkan NIP Anda untuk mereset password ke default</p>
                </div>

                <!-- Success -->
                <div v-if="status === 'reset_success'" class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
                        <div>
                            <p class="text-sm font-semibold text-green-800">Password berhasil direset!</p>
                            <p class="mt-1 text-sm text-green-700">Password telah direset menjadi password default.</p>
                            <p class="mt-2 text-xs text-green-600">Silakan login dan Anda akan diminta untuk mengganti password baru.</p>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form v-if="status !== 'reset_success'" @submit.prevent="submit" class="space-y-5">
                    <div class="space-y-2">
                        <Label for="nip" class="text-sm font-medium text-gray-700">NIP</Label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                <svg class="h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" /></svg>
                            </div>
                            <Input id="nip" v-model="form.nip" type="text" required autofocus autocomplete="off" placeholder="Masukkan NIP Anda" class="h-12 pl-11 text-sm" />
                        </div>
                        <InputError :message="form.errors.nip" />
                    </div>

                    <Button
                        type="submit"
                        class="h-12 w-full bg-gradient-to-r from-blue-600 to-blue-700 text-sm font-semibold shadow-lg shadow-blue-500/25 transition-all hover:from-blue-700 hover:to-blue-800"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" class="mr-2" />
                        {{ form.processing ? 'Memproses...' : 'Reset Password' }}
                    </Button>
                </form>

                <div class="mt-6 text-center">
                    <Link :href="login()" class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 transition-colors hover:text-blue-800">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7" /><path d="M19 12H5" /></svg>
                        Kembali ke halaman login
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
