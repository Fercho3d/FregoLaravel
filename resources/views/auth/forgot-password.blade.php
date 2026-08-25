<x-guest-layout :title="'Recuperar contraseña'">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-ink">¿Olvidaste tu contraseña?</h1>
        <p class="mt-1 text-sm text-ink-muted">Ingresa tu correo y te enviaremos un enlace para restablecerla.</p>
    </div>

    @include('partials.session-status')
    @include('partials.validation-errors')

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div>
            <label for="email" class="field-label">Correo</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   autocomplete="email" class="field-input mt-1.5" placeholder="correo@frego.com">
        </div>
        <x-submit-button class="w-full">Enviar enlace de restablecimiento</x-submit-button>
        <a href="{{ route('login') }}" class="block text-center text-sm text-ink-muted hover:text-ink-soft">Volver a iniciar sesión</a>
    </form>
</x-guest-layout>
