<?php

/*
|--------------------------------------------------------------------------
| Helpers globais
|--------------------------------------------------------------------------
*/

if (! function_exists('trans_replace')) {
    /**
     * Substitui placeholders ":chave" numa string já traduzida (mesma sintaxe
     * do __()), para textos longos que vêm como arrays de secções.
     *
     * @param  array<string, string|int|null>  $replacements
     */
    function trans_replace(string $text, array $replacements): string
    {
        // Chaves mais longas primeiro, para ":address" não ser afetado por ":a".
        uksort($replacements, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($replacements as $key => $value) {
            $text = str_replace(':'.$key, (string) ($value ?? '—'), $text);
        }

        return $text;
    }
}
