<x-guest-layout :title="'Restablecer contraseña'">
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-white">Nueva contraseña</h1>
        <p class="mt-1 text-sm text-frego-400">Define una contraseña segura para tu cuenta.</p>
    </div>

    @include('partials.validation-errors')

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="field-label">Correo</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required
                   autocomplete="email" class="field-input mt-1.5">
        </div>
        <div>
            <label for="password" class="field-label">Nueva contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="field-input mt-1.5" placeholder="••••••••">
        </div>
        <div>
            <label for="password_confirmation" class="field-label">Confirmar contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   autocomplete="new-password" class="field-input mt-1.5" placeholder="••••••••">
        </div>

        <button type="submit" class="btn-accent w-full">Restablecer contraseña</button>
    </form>
</x-guest-layout>
