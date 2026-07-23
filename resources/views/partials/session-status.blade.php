@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-800 bg-emerald-950/60 px-4 py-3 text-sm text-emerald-300">
        {{ session('status') }}
    </div>
@endif
