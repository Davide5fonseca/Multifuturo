<?php

namespace App\Console\Commands;

use App\Models\PropertyAlert;
use App\Notifications\PropertyAlertDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Envia os alertas de imóveis: para cada alerta confirmado, os imóveis
 * publicados depois do último envio (ou da confirmação) que encaixem nos
 * critérios, num só email. Corre de hora a hora (routes/console.php).
 *
 * "Novo" é published_at, escrito pelo observer na primeira vez que a ficha
 * fica publicável — uma edição ou uma mudança de preço não reenviam nada.
 * last_sent_at guarda o published_at do último imóvel enviado, não a hora
 * do envio: assim nada fica pelo caminho entre a pesquisa e o envio.
 */
class AlertsSend extends Command
{
    protected $signature = 'alerts:send';

    protected $description = 'Envia os alertas de imóveis com as novidades desde o último envio';

    /** Imóveis por email; o resto vai no envio seguinte. */
    private const MAX_PER_EMAIL = 10;

    public function handle(): int
    {
        $sent = 0;
        $alerts = PropertyAlert::query()->active()->orderBy('id')->get();

        foreach ($alerts as $alert) {
            $since = $alert->last_sent_at ?? $alert->confirmed_at;

            $properties = $alert->matches()
                ->whereNotNull('published_at')
                ->where('published_at', '>', $since)
                ->orderBy('published_at')
                ->orderBy('id')
                ->limit(self::MAX_PER_EMAIL)
                ->get();

            if ($properties->isEmpty()) {
                continue;
            }

            Notification::route('mail', $alert->email)
                ->notify((new PropertyAlertDigest($alert, $properties))->locale($alert->locale));

            $alert->forceFill([
                'last_sent_at' => $properties->max('published_at'),
                'sent_count' => $alert->sent_count + 1,
            ])->save();

            $sent++;
        }

        $this->info(sprintf('Alertas: %d ativos, %d emails enviados.', $alerts->count(), $sent));

        return self::SUCCESS;
    }
}
