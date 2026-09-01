<x-layouts.portal title="Verificação">
    <div class="mx-auto max-w-md">
        <p class="label">{{ config('agency.name') }}</p>
        <h1 class="mt-3 text-4xl">Verificação</h1>
        <p class="mt-3 text-ink-muted">Enviámos um código de seis algarismos para <strong class="text-ink">{{ $maskedEmail }}</strong>.</p>

        <form method="post" action="{{ route('mfa.verify') }}" class="mt-8 grid gap-5 rounded-2xl border border-sand-200 bg-sand-100 p-6 sm:p-8" novalidate>
            @csrf
            <div>
                <label for="code" class="label">Código</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus
                       placeholder="······" class="field mt-2 text-center font-serif text-3xl tracking-[0.5em] @error('code') border-error @enderror"
                       @error('code') aria-invalid="true" aria-describedby="code-erro" @enderror>
                @error('code')<p id="code-erro" class="mt-1 text-xs text-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <button type="submit" class="btn-primary w-full">Entrar</button>
            </div>
        </form>

        <form method="post" action="{{ route('mfa.resend') }}" class="mt-6 text-sm text-ink-muted">
            @csrf
            Não recebeu? <button type="submit" class="link">Enviar novo código</button>
        </form>
        <p class="mt-3 text-sm"><a href="{{ route('login') }}" class="link">← Usar outra conta</a></p>
    </div>
</x-layouts.portal>
