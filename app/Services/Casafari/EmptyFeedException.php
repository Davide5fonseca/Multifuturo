<?php

namespace App\Services\Casafari;

use RuntimeException;

/**
 * Lançada quando o feed devolve menos imóveis do que o mínimo aceitável
 * (zero, ou abaixo de casafari.min_items). É lançada ANTES de qualquer
 * desativação: uma resposta vazia do Feedcruncher não pode apagar a carteira.
 */
class EmptyFeedException extends RuntimeException {}
