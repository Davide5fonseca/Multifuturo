<x-layouts.portal title="Equipa e acessos">
    <div class="p-cabecalho">
        <div>
            <p class="p-eyebrow">Gestão</p>
            <h1 class="p-titulo">Equipa e acessos</h1>
            <p class="p-subtitulo">Quem entra no portal, quem é administrador e que módulos cada pessoa vê.</p>
        </div>
        <a href="{{ route('team.create') }}" class="p-btn p-btn--primario p-btn--auto">Nova conta</a>
    </div>

    <div class="p-tabela-caixa">
        <table class="p-tabela">
            <thead>
                <tr>
                    <th>Pessoa</th>
                    <th>Perfil</th>
                    <th>Módulos</th>
                    <th>Estado</th>
                    <th>Última entrada</th>
                    <th class="p-tabela__acoes"><span class="p-oculto">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $pessoa)
                    @php $modulos = $pessoa->isAdmin() ? 'Todos' : (\App\Support\Modules::forUser($pessoa)->reject(fn ($m) => $m['public'])->pluck('name')->implode(', ') ?: '—'); @endphp
                    <tr>
                        <td>
                            <span class="p-tabela__nome">{{ $pessoa->name }}</span>
                            <span class="p-tabela__sub">{{ $pessoa->email }}</span>
                        </td>
                        <td>@if ($pessoa->isAdmin())<span class="p-etiqueta p-etiqueta--acao">Administrador</span>@else<span class="p-tabela__sub">Utilizador</span>@endif</td>
                        <td>{{ $modulos }}</td>
                        <td>@if ($pessoa->is_active)<span class="p-etiqueta p-etiqueta--ok">Ativa</span>@else<span class="p-etiqueta p-etiqueta--cinza">Desativada</span>@endif</td>
                        <td><span class="p-tabela__sub">{{ $pessoa->last_login_at ? $pessoa->last_login_at->timezone(config('app.timezone'))->diffForHumans() : 'nunca' }}</span></td>
                        <td class="p-tabela__acoes"><a href="{{ route('team.edit', $pessoa) }}">Editar</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="p-nota">Os administradores veem todos os módulos. O Site é público: qualquer conta ativa o vê. Desativar uma conta põe a pessoa fora no pedido seguinte e mantém o histórico — apagar só quando for mesmo para apagar.</p>
</x-layouts.portal>
