<x-app-layout :title="'Panel'">
    <div class="mx-auto max-w-6xl space-y-6">
        <div class="rounded-2xl border border-line bg-gradient-to-br from-panel to-surface p-6 sm:p-8">
            <h2 class="text-xl font-semibold text-ink">
                Bienvenido, {{ auth()->user()->name ?? auth()->user()->username }}
            </h2>
            <p class="mt-1 text-sm text-ink-muted">
                Sistema FregoCargo — plataforma unificada de operación, facturación y portal.
            </p>
            @unless (auth()->user()->two_factor_secret)
                <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-accent-700/50 bg-accent-700/10 px-4 py-3 text-sm">
                    <span class="text-brand">Refuerza tu cuenta activando la verificación en dos pasos.</span>
                    <a href="{{ route('security.show') }}" class="btn-accent !py-1.5 !px-3 text-xs">Activar 2FA</a>
                </div>
            @endunless
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Catálogos', '14 catálogos maestros', 'Etapa 3'],
                ['Operación', 'Bookings y contenedores', 'Etapa 4'],
                ['Facturación', 'CFDI y timbrado', 'Etapa 5'],
                ['Pagos y bancos', 'Conciliación', 'Etapa 6'],
                ['Portal', 'Proveedores y clientes', 'Etapa 7'],
                ['Reportes', 'Utilidad y pagos', 'Etapa 6'],
            ] as [$t, $d, $stage])
                <div class="rounded-xl border border-line bg-panel p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-ink">{{ $t }}</h3>
                        <span class="rounded-full bg-raised px-2 py-0.5 text-[10px] font-medium text-ink-muted">{{ $stage }}</span>
                    </div>
                    <p class="mt-1 text-sm text-ink-muted">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
