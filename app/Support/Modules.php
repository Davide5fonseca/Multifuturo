<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Os módulos do portal (config/modules.php) e quem entra em qual.
 */
final class Modules
{
    /**
     * Todos os módulos ativos, pela ordem de apresentação, com a chave incluída.
     *
     * @return Collection<string, array{key: string, name: string, description: string, icon: string, url: string, panel: ?string, public: bool, new_tab: bool, order: int}>
     */
    public static function all(): Collection
    {
        return collect(config('modules', []))
            ->filter(fn (array $m) => $m['active'] ?? true)
            ->map(fn (array $m, string $key) => [
                'key' => $key,
                'name' => $m['name'],
                'description' => $m['description'] ?? '',
                'icon' => $m['icon'] ?? 'module',
                'url' => isset($m['route']) ? route($m['route'], $m['params'] ?? []) : (string) ($m['url'] ?? '#'),
                'panel' => $m['panel'] ?? null,
                'public' => (bool) ($m['public'] ?? false),
                'new_tab' => (bool) ($m['new_tab'] ?? false),
                'order' => (int) ($m['order'] ?? 0),
            ])
            ->sortBy([['order', 'asc'], ['name', 'asc']]);
    }

    /** @return array{key: string, name: string, description: string, icon: string, url: string, panel: ?string, public: bool, new_tab: bool, order: int}|null */
    public static function find(string $key): ?array
    {
        return self::all()->get($key);
    }

    /** Chave do módulo que protege um painel do Filament (null se o painel não é um módulo). */
    public static function keyForPanel(string $panelId): ?string
    {
        return collect(config('modules', []))
            ->search(fn (array $m) => ($m['panel'] ?? null) === $panelId) ?: null;
    }

    /**
     * Os módulos que esta pessoa pode abrir: todos, se for administradora;
     * senão, os públicos e os que lhe foram atribuídos.
     *
     * @return Collection<string, array{key: string, name: string, description: string, icon: string, url: string, panel: ?string, public: bool, new_tab: bool, order: int}>
     */
    public static function forUser(User $user): Collection
    {
        $all = self::all();

        if ($user->isAdmin()) {
            return $all;
        }

        $keys = $user->moduleAccess()->pluck('module')->all();

        return $all->filter(fn (array $m) => $m['public'] || in_array($m['key'], $keys, true));
    }

    /**
     * Opções para o formulário da equipa: só os módulos com acesso controlado.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::all()->reject(fn (array $m) => $m['public'])->pluck('name', 'key')->all();
    }
}
