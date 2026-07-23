<x-app-layout :title="'Seguridad'">
    @php
        $user = auth()->user();
        $twoFactorEnabled = ! is_null($user->two_factor_secret);
        $twoFactorConfirmed = ! is_null($user->two_factor_confirmed_at);
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">
        @include('partials.validation-errors')

        {{-- Cambio de contraseña --}}
        <section class="rounded-2xl border border-frego-800 bg-frego-900/60 p-6">
            <h2 class="text-base font-semibold text-white">Cambiar contraseña</h2>
            <p class="mt-1 text-sm text-frego-400">Usa una contraseña larga y única para esta cuenta.</p>

            <form method="POST" action="/user/password" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="current_password" class="field-label">Contraseña actual</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="field-input mt-1.5">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="field-label">Nueva contraseña</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" class="field-input mt-1.5">
                    </div>
                    <div>
                        <label for="password_confirmation" class="field-label">Confirmar</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="field-input mt-1.5">
                    </div>
                </div>
                <button type="submit" class="btn-accent">Actualizar contraseña</button>
            </form>
        </section>

        {{-- Verificación en dos pasos --}}
        <section class="rounded-2xl border border-frego-800 bg-frego-900/60 p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-white">Verificación en dos pasos (2FA)</h2>
                    <p class="mt-1 text-sm text-frego-400">Añade una capa extra con una app de autenticación (Google Authenticator, Authy…).</p>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $twoFactorConfirmed ? 'bg-emerald-900/60 text-emerald-300' : 'bg-frego-800 text-frego-400' }}">
                    {{ $twoFactorConfirmed ? 'Activada' : 'Inactiva' }}
                </span>
            </div>

            @if (! $twoFactorEnabled)
                <form method="POST" action="/user/two-factor-authentication" class="mt-4">
                    @csrf
                    <button type="submit" class="btn-accent">Activar 2FA</button>
                </form>
            @else
                @if (! $twoFactorConfirmed)
                    <div class="mt-4 space-y-4">
                        <p class="text-sm text-frego-300">1. Escanea este código QR con tu app de autenticación:</p>
                        <div class="inline-block rounded-xl bg-white p-3">
                            {!! $user->twoFactorQrCodeSvg() !!}
                        </div>
                        <p class="text-xs text-frego-500">
                            ¿No puedes escanear? Clave manual:
                            <code class="rounded bg-frego-800 px-1.5 py-0.5 text-frego-200">{{ decrypt($user->two_factor_secret) }}</code>
                        </p>
                        <form method="POST" action="/user/confirmed-two-factor-authentication" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label for="code" class="field-label">2. Ingresa el código generado</label>
                                <input id="code" name="code" type="text" inputmode="numeric" class="field-input mt-1.5 w-40 tracking-widest text-center" placeholder="000000">
                            </div>
                            <button type="submit" class="btn-accent">Confirmar</button>
                        </form>
                    </div>
                @else
                    <div class="mt-4 space-y-4">
                        <div>
                            <p class="text-sm font-medium text-frego-200">Códigos de recuperación</p>
                            <p class="text-xs text-frego-500">Guárdalos en un lugar seguro; te permiten entrar si pierdes tu dispositivo.</p>
                            <div class="mt-2 grid grid-cols-2 gap-1.5 rounded-lg border border-frego-800 bg-frego-950 p-3 font-mono text-xs text-frego-300 sm:grid-cols-4">
                                @foreach (json_decode(decrypt($user->two_factor_recovery_codes), true) as $rc)
                                    <span>{{ $rc }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <form method="POST" action="/user/two-factor-recovery-codes">@csrf<button class="btn-ghost">Regenerar códigos</button></form>
                            <form method="POST" action="/user/two-factor-authentication">
                                @csrf @method('DELETE')
                                <button class="btn-ghost !border-accent-700 !text-accent-400">Desactivar 2FA</button>
                            </form>
                        </div>
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-app-layout>
