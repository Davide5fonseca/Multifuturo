<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Idiomas do site (ver config/locales.php).
 *
 * O idioma vive no primeiro segmento do endereço (/pt/comprar, /en/comprar) e
 * é um parâmetro de rota com valor por omissão, o que faz com que todos os
 * route('buy') do projeto continuem a funcionar sem saber de idiomas: geram
 * sempre o endereço do idioma que está a ser servido.
 */
final class Locales
{
    /** @return array<int, string> */
    public static function enabled(): array
    {
        $available = array_keys(config('locales.available', []));

        $enabled = array_values(array_intersect(
            (array) config('locales.enabled', []),
            $available
        ));

        // Sem configuração válida, fica pelo menos o idioma por omissão.
        return $enabled !== [] ? $enabled : [self::default()];
    }

    public static function default(): string
    {
        return (string) config('locales.default', 'pt');
    }

    public static function isEnabled(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::enabled(), true);
    }

    /** Expressão para o where() das rotas: "pt|en". */
    public static function pattern(): string
    {
        return implode('|', self::enabled());
    }

    public static function label(string $locale): string
    {
        return (string) config("locales.available.{$locale}.label", $locale);
    }

    public static function short(string $locale): string
    {
        return (string) config("locales.available.{$locale}.short", mb_strtoupper($locale));
    }

    /** Valor do atributo lang="" e do hreflang ("pt-PT", "en"). */
    public static function htmlLang(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) config("locales.available.{$locale}.html", $locale);
    }

    /** Multilingue só quando há mais do que um idioma ligado. */
    public static function isMultilingual(): bool
    {
        return count(self::enabled()) > 1;
    }

    /**
     * O endereço da página atual noutro idioma. Mantém a rota e os parâmetros
     * (incluindo o slug do imóvel) e troca apenas o idioma; a query string dos
     * filtros segue igual.
     */
    public static function switchUrl(string $locale, bool $withQuery = true): string
    {
        $route = Route::current();

        if ($route === null || $route->getName() === null) {
            return URL::to('/'.$locale);
        }

        $parameters = array_merge($route->parameters(), ['locale' => $locale]);
        $query = $withQuery ? request()->getQueryString() : null;

        return route($route->getName(), $parameters).($query ? '?'.$query : '');
    }

    /**
     * Alternativas para as etiquetas hreflang.
     *
     * @return array<string, string> idioma html => endereço
     */
    public static function alternates(): array
    {
        if (! self::isMultilingual()) {
            return [];
        }

        // Sem a query string, como o canonical: as alternativas apontam para a
        // página base, não para cada combinação de filtros.
        $alternates = [];
        foreach (self::enabled() as $locale) {
            $alternates[self::htmlLang($locale)] = self::switchUrl($locale, withQuery: false);
        }

        return $alternates;
    }
}
