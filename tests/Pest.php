<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest
|--------------------------------------------------------------------------
|
| Todos os testes Feature correm com a base de dados de testes (PostgreSQL
| "testing", criada pelo Sail) migrada de fresco — o schema usa jsonb e GIN,
| por isso não se testa em SQLite.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
