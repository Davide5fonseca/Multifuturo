<?php

namespace App\Http\Requests\Concerns;

/**
 * Anti-spam sem CAPTCHA de terceiros, partilhado pelos formulários públicos
 * (leads e alertas de imóveis):
 *  - honeypot: o campo "website" está escondido por CSS; humanos deixam-no
 *    vazio, bots preenchem-no. Se vier preenchido, o pedido é aceite em
 *    silêncio (redirect) mas não grava nada — não vale a pena ensinar o bot;
 *  - tempo mínimo: o formulário traz a hora em que foi renderizado, assinada
 *    com a APP_KEY; submissões em menos de MIN_SECONDS são tratadas como spam;
 *  - rate limiting por IP no route (limiter "leads", AppServiceProvider).
 */
trait GuardsAgainstBots
{
    /** Segundos mínimos entre renderizar o formulário e submeter. */
    public const MIN_SECONDS = 3;

    /**
     * True quando o pedido tem cheiro de bot: honeypot preenchido ou submissão
     * demasiado rápida. Avaliado DEPOIS da validação (o "website" com max:0
     * falharia a validação com mensagem — preferimos aceitar em silêncio).
     */
    public function looksLikeSpam(): bool
    {
        if (filled($this->input('website'))) {
            return true;
        }

        $ts = $this->input('form_ts');
        if (is_string($ts) && $ts !== '') {
            $renderedAt = self::verifyTimestamp($ts);
            if ($renderedAt === null || (time() - $renderedAt) < self::MIN_SECONDS) {
                return true;
            }
        }

        return false;
    }

    /** Timestamp assinado com a APP_KEY, para o formulário não poder forjar a hora. */
    public static function signedTimestamp(?int $time = null): string
    {
        $time ??= time();

        return $time.'.'.hash_hmac('sha256', (string) $time, (string) config('app.key'));
    }

    private static function verifyTimestamp(string $value): ?int
    {
        [$time, $sig] = array_pad(explode('.', $value, 2), 2, '');

        if (! ctype_digit($time) || ! hash_equals(hash_hmac('sha256', $time, (string) config('app.key')), $sig)) {
            return null;
        }

        return (int) $time;
    }
}
