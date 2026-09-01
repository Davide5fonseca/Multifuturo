<x-layouts.portal title="Entrar" :entrada="true">
    <h1>Entrar</h1>
    <p class="p-intro">Bem-vindo de volta. Use a sua conta da equipa.</p>

    <form method="post" action="{{ route('login.store') }}" class="p-form" novalidate>
        @csrf

        <div class="p-campo">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus placeholder="nome@empresa.pt"
                   @error('email') aria-invalid="true" aria-describedby="email-erro" @enderror>
            @error('email')<p id="email-erro" class="p-erro" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="p-campo">
            <div class="p-campo__cabecalho">
                <label for="password">Palavra-passe</label>
                <a href="{{ route('filament.admin.auth.password-reset.request') }}">Esqueceu-se?</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   @error('password') aria-invalid="true" @enderror>
            @error('password')<p class="p-erro" role="alert">{{ $message }}</p>@enderror
        </div>

        <label class="p-caixa">
            <input type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
            <span>Manter sessão iniciada</span>
        </label>

        <button type="submit" class="p-btn p-btn--primario">{{ config('portal.mfa') ? 'Continuar' : 'Entrar' }}</button>
    </form>

    @if (config('portal.mfa'))
        <p class="p-ajuda">A seguir enviamos-lhe um código de seis algarismos para o email.</p>
    @endif
</x-layouts.portal>
