<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O acesso de uma pessoa a um módulo do portal (config/modules.php).
 *
 * @property int $user_id
 * @property string $module
 * @property ?string $role
 */
class ModuleAccess extends Model
{
    protected $table = 'module_access';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
