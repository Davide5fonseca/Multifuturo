<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Utilizador da equipa da agência — só existe para entrar no backoffice (/admin).
 * O site público não tem registo nem login; estas contas são criadas por um
 * administrador (ver README).
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
        ];
    }

    /**
     * Quem pode entrar no backoffice: qualquer conta existente. Como não há
     * registo público, criar a conta É a autorização. (O Filament exige esta
     * decisão explícita fora do ambiente local.)
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
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
