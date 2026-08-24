<?php

namespace App\Enums;

/**
 * Finalidade do imóvel — as mesmas seis do CRM que substituímos. Os valores são
 * os guardados na coluna business_type; os rótulos e o mapeamento para as
 * páginas públicas vivem em métodos, para que as rotas e as listagens não
 * dependam de strings soltas.
 *
 * O site só tem duas listagens (/comprar e /arrendar), por isso cada finalidade
 * declara em qual (ou quais) entra — ver listings().
 */
enum BusinessType: string
{
    case Sale = 'sale';                 // Venda
    case Rent = 'rent';                 // Arrendamento ao ano
    case Transfer = 'transfer';         // Trespasse
    case Exchange = 'exchange';         // Permuta
    case ShortTermRent = 'rent_short';  // Arrendamento curto prazo / férias
    case RentOrSale = 'rent_sale';      // Arrendamento / venda

    /**
     * Listagens públicas em que a finalidade entra: 'buy' (/comprar) e/ou
     * 'rent' (/arrendar). Trespasse e permuta entram em "comprar" porque é aí
     * que quem procura os vai encontrar; "arrendamento / venda" entra nas duas.
     *
     * @return array<int, string>
     */
    public function listings(): array
    {
        return match ($this) {
            self::Sale, self::Transfer, self::Exchange => ['buy'],
            self::Rent, self::ShortTermRent => ['rent'],
            self::RentOrSale => ['buy', 'rent'],
        };
    }

    /** O preço é uma renda mensal (leva o "/mês" à frente). */
    public function isRent(): bool
    {
        return in_array($this, [self::Rent, self::ShortTermRent], true);
    }

    /** Listagem principal — usada na navegação e no rasto da ficha. */
    public function routeName(): string
    {
        return in_array('buy', $this->listings(), true) ? 'buy' : 'rent';
    }

    public function label(): string
    {
        return __('ui.business.'.$this->value);
    }

    /**
     * Valores que entram numa listagem, para o whereIn das consultas.
     *
     * @return array<int, string>
     */
    public static function forListing(string $route): array
    {
        return array_values(array_map(
            fn (self $case) => $case->value,
            array_filter(self::cases(), fn (self $case) => in_array($route, $case->listings(), true)),
        ));
    }

    /** Opções para os selects do backoffice. @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            function (array $carry, self $case): array {
                $carry[$case->value] = $case->label();

                return $carry;
            },
            [],
        );
    }

    public static function fromRouteName(string $route): self
    {
        return match ($route) {
            'buy' => self::Sale,
            'rent' => self::Rent,
        };
    }
}
