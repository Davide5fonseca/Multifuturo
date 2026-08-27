<?php

namespace App\Models;

use App\Support\PropertyCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Valor de referência por m² (concelho × tipo) para a estimativa imediata.
 *
 * @property string $city
 * @property string $locality '' = concelho inteiro; senão, a freguesia
 * @property string $property_type apartment | house | land
 * @property string $price_per_m2
 * @property ?string $notes
 * @property string $source manual | ine
 */
class ReferencePrice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price_per_m2' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        // A tabela do simulador vive na cache dos imóveis; qualquer alteração
        // aqui tem de chegar ao site na hora.
        static::saved(fn () => PropertyCache::flush());
        static::deleted(fn () => PropertyCache::flush());
    }
}
