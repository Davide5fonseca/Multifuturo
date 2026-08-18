<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Conteúdo editorial de uma página de zona (concelho ou freguesia).
 *
 * @property string $city_slug
 * @property ?string $locality_slug
 * @property ?string $title
 * @property ?string $meta_description
 * @property ?string $intro
 * @property ?string $body
 * @property ?string $cover_url
 * @property bool $is_published
 */
class Zone extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
