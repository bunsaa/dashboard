<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const showPassword = ref(false);
const captchaImage = ref('');
const captchaInput = ref('');
const captchaLoading = ref(false);

const refreshCaptcha = async () => {
    captchaLoading.value = true;
    try {
        const res = await fetch('/captcha');
        const data = await res.json();
        captchaImage.value = data.image;
    } catch {
        captchaImage.value = '';
    } finally {
        captchaLoading.value = false;
    }
};

const onFormError = () => {
    captchaInput.value = '';
    refreshCaptcha();
};

onMounted(() => refreshCaptcha());
</script>

<template>
    <Head title="Login — Dashboard RSUD Tarakan" />

    <!-- Full-screen dark background -->
    <div
        class="relative flex min-h-screen w-full items-center justify-center px-4 py-10"
        style="background: linear-gradient(150deg, #020c22 0%, #071d4a 45%, #0c3274 80%, #0d3d8f 100%);"
    >
        <!-- Grid texture -->
        <div
            class="pointer-events-none absolute inset-0"
            style="background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px); background-size: 48px 48px;"
        ></div>

        <!-- Glow top-right -->
        <div
            class="pointer-events-none absolute right-0 top-0 h-[500px] w-[500px] -translate-y-1/3 translate-x-1/3 opacity-25"
            style="background: radial-gradient(circle, #1d4ed8 0%, transparent 60%); filter: blur(70px);"
        ></div>
        <!-- Glow bottom-left -->
        <div
            class="pointer-events-none absolute bottom-0 left-0 h-[400px] w-[400px] -translate-x-1/3 translate-y-1/3 opacity-20"
            style="background: radial-gradient(circle, #0891b2 0%, transparent 60%); filter: blur(60px);"
        ></div>

        <!-- Cross watermark -->
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center opacity-[0.025]">
            <svg width="520" height="520" viewBox="0 0 520 520" fill="white">
                <rect x="193" y="0" width="134" height="520" rx="22" />
                <rect x="0" y="193" width="520" height="134" rx="22" />
            </svg>
        </div>

        <!-- Card -->
        <div class="relative z-10 w-full max-w-[420px]">

            <!-- Logo & brand header -->
            <div class="mb-6 flex flex-col items-center text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-white/10 ring-1 ring-white/20 backdrop-blur-sm">
                    <img
                        src="/images/logo-rsud-tarakan.png"
                        alt="RSUD Tarakan"
                        class="h-12 w-12 object-contain"
                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                    />
                </div>
                <h1 class="text-[15px] font-extrabold uppercase tracking-[0.15em] text-sky-400">RSUD Tarakan</h1>
                <p class="mt-0.5 text-[11px] text-white/35">PROV. DKI JAKARTA</p>
            </div>

            <!-- Form card -->
            <div class="overflow-hidden rounded-2xl bg-white shadow-[0_32px_80px_-12px_rgba(0,0,0,0.6)]">

                <!-- Card header -->
                <div class="border-b border-slate-100 px-7 py-5">
                    <h2 class="text-[20px] font-black text-slate-800">Masuk ke Sistem</h2>
                    <p class="mt-0.5 text-[13px] text-slate-400">Gunakan NIP dan password Anda</p>
                </div>

                <!-- Card body -->
                <div class="px-7 py-6">

                    <!-- Status message -->
                    <div v-if="status" class="mb-5 flex items-center gap-2.5 rounded-xl border border-green-200 bg-green-50 px-4 py-3">
                        <svg class="h-4 w-4 shrink-0 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
                        <p class="text-[13px] font-medium text-green-700">{{ status }}</p>
                    </div>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password', 'captcha']"
                        @error="onFormError"
                        v-slot="{ errors, processing }"
                        class="space-y-4"
                    >
                        <!-- NIP -->
                        <div class="space-y-1.5">
                            <label for="nip" class="block text-[13px] font-semibold text-slate-600">NIP</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />
                                </svg>
                                <Input
                                    id="nip"
                                    type="text"
                                    name="nip"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="username"
                                    placeholder="Masukkan NIP Anda"
                                    class="h-11 pl-10 text-[13px]"
                                />
                            </div>
                            <InputError class="text-xs" :message="errors.nip" />
                        </div>

                        <!-- Password -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label for="password" class="text-[13px] font-semibold text-slate-600">Password</label>
                                <Link
                                    v-if="canResetPassword"
                                    :href="request()"
                                    :tabindex="6"
                                    class="text-[12px] font-medium text-blue-500 transition-colors hover:text-blue-700"
                                >
                                    Lupa password?
                                </Link>
                            </div>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0110 0v4" />
                                </svg>
                                <Input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    placeholder="Masukkan password"
                                    class="h-11 pl-10 pr-11 text-[13px]"
                                />
                                <button
                                    type="button"
                                    :tabindex="7"
                                    class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 transition-colors hover:text-slate-600"
                                    @click="showPassword = !showPassword"
                                >
                                    <svg v-if="!showPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" /><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" /><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" /><line x1="2" x2="22" y1="2" y2="22" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="text-xs" :message="errors.password" />
                        </div>

                        <!-- Captcha -->
                        <div class="space-y-1.5">
                            <label class="block text-[13px] font-semibold text-slate-600">Verifikasi Keamanan</label>
                            <div class="rounded-xl border border-slate-200 bg-slate-50/80">
                                <div class="flex items-center gap-2 p-2">
                                    <!-- Image box -->
                                    <div class="flex h-10 w-[90px] shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white">
                                        <img
                                            v-if="captchaImage && !captchaLoading"
                                            :src="captchaImage"
                                            alt="Captcha"
                                            class="h-full w-full object-contain"
                                        />
                                        <div v-else class="flex items-center gap-1 text-[10px] text-slate-400">
                                            <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            Memuat
                                        </div>
                                    </div>
                                    <!-- Refresh button -->
                                    <button
                                        type="button"
                                        :disabled="captchaLoading"
                                        title="Ganti soal"
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition-all hover:border-blue-300 hover:text-blue-500 disabled:opacity-40"
                                        @click="refreshCaptcha"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            :class="{ 'animate-spin': captchaLoading }"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        >
                                            <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2" />
                                        </svg>
                                    </button>
                                    <!-- Answer -->
                                    <Input
                                        id="captcha"
                                        type="text"
                                        name="captcha"
                                        v-model="captchaInput"
                                        required
                                        :tabindex="3"
                                        autocomplete="off"
                                        placeholder="Jawaban"
                                        class="h-10 flex-1 text-[13px]"
                                    />
                                </div>
                                <p class="border-t border-slate-100 px-3 py-2 text-[11px] text-slate-400">
                                    Hitung hasil operasi matematika pada gambar di atas
                                </p>
                            </div>
                            <InputError class="text-xs" :message="errors.captcha" />
                        </div>

                        <!-- Remember me -->
                        <div class="flex items-center gap-2.5">
                            <Checkbox id="remember" name="remember" :tabindex="4" />
                            <label for="remember" class="cursor-pointer select-none text-[13px] text-slate-500">
                                Ingat saya di perangkat ini
                            </label>
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :tabindex="5"
                            :disabled="processing"
                            class="flex h-11 w-full items-center justify-center gap-2 rounded-xl text-[13.5px] font-bold text-white shadow-lg shadow-blue-700/25 transition-all hover:brightness-110 active:scale-[.99] disabled:opacity-70"
                            style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #0369a1 100%);"
                        >
                            <Spinner v-if="processing" />
                            <template v-if="!processing">
                                Masuk ke Sistem
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14" /><path d="m12 5 7 7-7 7" />
                                </svg>
                            </template>
                            <template v-if="processing">Memproses...</template>
                        </button>
                    </Form>
                </div>
            </div>

            <!-- Footer -->
            <p class="mt-5 text-center text-[11px] text-white/25">
                &copy; {{ new Date().getFullYear() }}
                <span class="text-white/40">Ukfia Anggraini — RSUD Tarakan</span>
            </p>
        </div>
    </div>
</template>
