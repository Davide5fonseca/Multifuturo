<?php

namespace App\Models;

use App\Support\Modules;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Utilizador da equipa da agência. Entra pelo portal (/entrar) e escolhe o
 * módulo onde vai trabalhar; o backoffice de imóveis (/admin) é o primeiro.
 * O site público não tem registo nem login; estas contas são criadas por um
 * administrador (Equipa, no backoffice).
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Os módulos a que esta pessoa tem acesso explícito (os administradores não precisam). */
    public function moduleAccess(): HasMany
    {
        return $this->hasMany(ModuleAccess::class);
    }

    /** Pode abrir este módulo? Administradores abrem todos. */
    public function canAccessModule(string $module): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->moduleAccess()->where('module', $module)->exists();
    }

    /**
     * Substitui os acessos por esta lista de chaves de módulos.
     *
     * @param  list<string>  $modules
     */
    public function syncModules(array $modules): void
    {
        $modules = array_values(array_unique(array_filter($modules)));

        $this->moduleAccess()->whereNotIn('module', $modules)->delete();

        foreach ($modules as $module) {
            $this->moduleAccess()->firstOrCreate(['module' => $module]);
        }
    }

    /**
     * Quem pode entrar num painel do Filament: contas ativas com acesso ao
     * módulo que o painel representa (config/modules.php). Um painel que não
     * seja módulo fica aberto a qualquer conta ativa.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Só uma conta desativada de propósito fica de fora (null = criada antes da coluna existir).
        if ($this->is_active === false) {
            return false;
        }

        $module = Modules::keyForPanel($panel->getId());

        return $module === null || $this->canAccessModule($module);
    }

    /**
     * Administrador: gere as contas da equipa. Não é um sistema de permissões
     * — é a distinção mínima para a equipa poder crescer sem ninguém tocar no
     * servidor.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
