@php
    $editing = $user->exists;
    $self = $editing && $user->is(auth()->user());
    $options = \App\Support\Modules::options();
    $selected = old('modules', $modules);
@endphp
<x-layouts.portal :title="$editing ? 'Editar conta' : 'Nova conta'">
    <div class="p-cabecalho">
        <div>
            <p class="p-eyebrow"><a href="{{ route('team.index') }}">Equipa e acessos</a></p>
            <h1 class="p-titulo">{{ $editing ? $user->name : 'Nova conta' }}</h1>
            <p class="p-subtitulo">{{ $editing ? $user->email : 'Cria a conta e define o que a pessoa vê no portal.' }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-alerta p-alerta--erro" role="alert">
            <div><strong>Não foi possível guardar.</strong>
                <ul class="p-alerta__lista">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <form method="post" action="{{ $editing ? route('team.update', $user) : route('team.store') }}" class="p-formulario" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="p-seccao">
            <h2 class="p-seccao__titulo">Conta</h2>
            <div class="p-grelha-2">
                <div class="p-campo">
                    <label for="name">Nome</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="191" autocomplete="off" @error('name') aria-invalid="true" @enderror>
                </div>
                <div class="p-campo">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="191" autocomplete="off" @error('email') aria-invalid="true" @enderror>
                    <p class="p-ajuda-campo">É com este endereço que entra no portal.</p>
                </div>
                <div class="p-campo p-grelha-2__cheio">
                    <label for="password">Palavra-passe {!! $editing ? '<span class="p-opcional">(deixe em branco para manter)</span>' : '' !!}</label>
                    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" @if (! $editing) required @endif @error('password') aria-invalid="true" @enderror>
                    <p class="p-ajuda-campo">Mínimo 8 caracteres. Dê-a à pessoa por um canal seguro — ela pode mudá-la no perfil.</p>
                </div>
            </div>
        </section>

        <section class="p-seccao">
            <h2 class="p-seccao__titulo">Permissões</h2>
            <div class="p-grelha-2">
                <label class="p-opcao {{ $self ? 'p-opcao--bloqueada' : '' }}">
                    <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin)) @disabled($self)>
                    <span><strong>Administrador</strong><span>Gere a equipa e os acessos e vê todos os módulos.{{ $self ? ' Não pode retirar o seu próprio acesso.' : '' }}</span></span>
                </label>
                <label class="p-opcao {{ $self ? 'p-opcao--bloqueada' : '' }}">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) @disabled($self)>
                    <span><strong>Conta ativa</strong><span>Desativar põe a pessoa fora no pedido seguinte e impede-a de entrar.{{ $self ? ' Não pode desativar a sua própria conta.' : '' }}</span></span>
                </label>
            </div>

            <div class="p-modulos-escolha">
                <p class="p-campo__rotulo">Módulos</p>
                <p class="p-ajuda-campo">O que esta pessoa vê na página de escolha. Os administradores veem sempre todos; o Site é público.</p>
                <div class="p-grelha-2">
                    @foreach ($options as $key => $name)
                        <label class="p-opcao">
                            <input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, (array) $selected, true))>
                            <span><strong>{{ $name }}</strong><span>{{ \App\Support\Modules::find($key)['description'] ?? '' }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="p-acoes">
            <button type="submit" class="p-btn p-btn--primario p-btn--auto">{{ $editing ? 'Guardar' : 'Criar conta' }}</button>
            <a href="{{ route('team.index') }}" class="p-btn p-btn--neutro">Cancelar</a>
        </div>
    </form>

    @if ($editing && ! $self)
        <form method="post" action="{{ route('team.destroy', $user) }}" class="p-perigo" onsubmit="return confirm('Apagar a conta de {{ addslashes($user->name) }}? Esta ação não se desfaz. Se for só para a impedir de entrar, desative-a.');">
            @csrf
            @method('DELETE')
            <span>Apagar a conta de forma definitiva.</span>
            <button type="submit" class="p-btn p-btn--perigo">Apagar conta</button>
        </form>
    @endif
</x-layouts.portal>
