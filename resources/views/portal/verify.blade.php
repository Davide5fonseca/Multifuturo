<x-layouts.portal title="Verificação" :entrada="true">
    <h1>Verificação</h1>
    <p class="p-intro">Enviámos um código de seis algarismos para <strong>{{ $maskedEmail }}</strong>.</p>

    <form method="post" action="{{ route('mfa.verify') }}" class="p-form" novalidate>
        @csrf
        <div class="p-campo">
            <label for="code">Código</label>
            <input id="code" name="code" type="text" class="p-codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus placeholder="······"
                   @error('code') aria-invalid="true" aria-describedby="code-erro" @enderror>
            @error('code')<p id="code-erro" class="p-erro" role="alert">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="p-btn p-btn--primario">Entrar</button>
    </form>

    {{-- div, não p: um <form> dentro de <p> é HTML inválido e o browser separa-os. --}}
    <div class="p-ajuda">Não recebeu? <form method="post" action="{{ route('mfa.resend') }}">@csrf<button type="submit" class="p-btn--ligacao">Enviar novo código</button></form></div>
    <p class="p-rodape-entrada"><a href="{{ route('login') }}">← Usar outra conta</a></p>
</x-layouts.portal>
