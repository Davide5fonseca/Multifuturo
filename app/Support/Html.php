<?php

namespace App\Support;

/**
 * HTML escrito no backoffice (texto "Website (HTML)" do imóvel) antes de ir
 * para a página pública. O editor já produz HTML limpo, mas o que sai para o
 * site nunca deve depender disso: fica só a formatação de texto, sem scripts,
 * sem atributos de evento e sem ligações javascript:.
 */
final class Html
{
    private const ALLOWED = '<p><br><strong><b><em><i><u><s><a><ul><ol><li><h2><h3><h4><blockquote>';

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Conteúdo de <script>/<style> vai fora inteiro, não só as etiquetas.
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, self::ALLOWED);

        // Todas as etiquetas perdem os atributos; <a> fica só com href seguro.
        $html = preg_replace_callback('#<a\b([^>]*)>#i', function (array $m): string {
            if (preg_match('#href\s*=\s*(["\'])(.*?)\1#i', $m[1], $h) === 1) {
                $href = trim(html_entity_decode($h[2]));
                if (preg_match('#^(https?://|mailto:|tel:|/)#i', $href) === 1) {
                    $seguro = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

                    return '<a href="'.$seguro.'" rel="noopener">';
                }
            }

            return '<a>';
        }, $html) ?? '';

        return preg_replace('#<(?!a\b|/)(\w+)\b[^>]*>#i', '<$1>', $html) ?? '';
    }
}
