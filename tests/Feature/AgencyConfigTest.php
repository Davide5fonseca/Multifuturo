<?php

/*
 * O número AMI é obrigatório por lei em toda a comunicação comercial de mediação
 * imobiliária (Lei n.º 15/2013). Um site em produção sem AMI é uma não-conformidade.
 * Estes testes garantem que a configuração falha ruidosamente nesse caso.
 */

use App\Support\AgencyCompliance;

it('falha se o AMI estiver vazio em produção', function () {
    config(['agency.ami' => null]);

    expect(fn () => AgencyCompliance::assertAmi('production'))
        ->toThrow(RuntimeException::class, 'AMI');
});

it('falha se o AMI estiver em branco em produção', function () {
    config(['agency.ami' => '   ']);

    expect(fn () => AgencyCompliance::assertAmi('production'))
        ->toThrow(RuntimeException::class);
});

it('aceita um AMI preenchido em produção', function () {
    config(['agency.ami' => '12345']);

    expect(AgencyCompliance::assertAmi('production'))->toBeTrue();
});

it('tolera AMI vazio fora de produção, para não bloquear o desenvolvimento', function () {
    config(['agency.ami' => null]);

    expect(AgencyCompliance::assertAmi('local'))->toBeTrue();
});

it('em produção, o AMI configurado neste ambiente não pode estar vazio', function () {
    // Este é o teste que "falha se estiver vazio quando APP_ENV=production":
    // corre contra a configuração real da instância.
    if (! app()->isProduction()) {
        $this->markTestSkipped('Só é exigido em produção.');
    }

    expect(config('agency.ami'))->not->toBeEmpty();
});
