<x-layouts.portal title="Entrar">
    <div class="mx-auto max-w-md">
        <p class="label">{{ config('agency.name') }}</p>
        <h1 class="mt-3 text-4xl">Entrar</h1>
        <p class="mt-3 text-ink-muted">A área da equipa: uma só entrada para todos os módulos.</p>

        <form method="post" action="{{ route('login.store') }}" class="mt-8 grid gap-5 rounded-2xl border border-sand-200 bg-sand-100 p-6 sm:p-8" novalidate>
            @csrf

            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus
                       class="field mt-2 @error('email') border-error @enderror" @error('email') aria-invalid="true" aria-describedby="email-erro" @enderror>
                @error('email')<p id="email-erro" class="mt-1 text-xs text-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-4">
                    <label for="password" class="label">Palavra-passe</label>
                    <a href="{{ route('filament.admin.auth.password-reset.request') }}" class="link text-xs">Esqueceu-se?</a>
                </div>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="field mt-2 @error('password') border-error @enderror">
                @error('password')<p class="mt-1 text-xs text-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 text-sm">
                <input type="checkbox" id="remember" name="remember" value="1" @checked(old('remember')) class="h-5 w-5 shrink-0 accent-olive-600">
                <label for="remember">Manter sessão iniciada</label>
            </div>

            <div>
                <button type="submit" class="btn-primary w-full">{{ config('portal.mfa') ? 'Continuar' : 'Entrar' }}</button>
            </div>

            @if (config('portal.mfa'))
                <p class="text-xs text-ink-muted">A seguir enviamos-lhe um código de seis algarismos para o email.</p>
            @endif
        </form>
    </div>
</x-layouts.portal>
