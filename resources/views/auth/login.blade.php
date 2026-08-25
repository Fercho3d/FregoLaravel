<x-guest-layout :title="'Iniciar sesión'">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-ink">{{ __('Iniciar sesión') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('Accede con tu usuario o correo.') }}</p>
    </div>

    @include('partials.session-status')
    @include('partials.validation-errors')

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="login" class="field-label">{{ __('Usuario o correo') }}</label>
            <input id="login" name="login" type="text" value="{{ old('login') }}"
                   required autofocus autocomplete="username"
                   class="field-input mt-1.5" placeholder="usuario o correo@frego.com">
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="field-label">{{ __('Contraseña') }}</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-brand hover:text-accent-500">
                        {{ __('¿Olvidaste tu contraseña?') }}
                    </a>
                @endif
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="field-input mt-1.5" placeholder="••••••••">
        </div>

        <label for="remember" class="flex items-center gap-2 text-sm text-ink-muted select-none">
            <input id="remember" name="remember" type="checkbox"
                   class="h-4 w-4 rounded border-line bg-panel text-accent-500 focus:ring-accent-500">
            {{ __('Recordar mi sesión en este equipo') }}
        </label>

        <x-submit-button class="w-full">{{ __('Entrar') }}</x-submit-button>
    </form>
</x-guest-layout>
