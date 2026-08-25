<x-guest-layout :title="'Confirmar contraseña'">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-ink">Confirma tu contraseña</h1>
        <p class="mt-1 text-sm text-ink-muted">Esta es una zona segura. Confirma tu contraseña para continuar.</p>
    </div>

    @include('partials.validation-errors')

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <div>
            <label for="password" class="field-label">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   autofocus class="field-input mt-1.5" placeholder="••••••••">
        </div>
        <x-submit-button class="w-full">Confirmar</x-submit-button>
    </form>
</x-guest-layout>
