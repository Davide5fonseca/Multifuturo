<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Jobs\SendLeadToCasafari;
use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * leads:retry — reenvia ao CRM as leads que ficaram por entregar.
 *
 * Casos de uso:
 *  - o CRM esteve em baixo mais tempo do que as 5 tentativas do job (leads "failed");
 *  - as leads foram criadas antes de haver CASAFARI_TOKEN (ficaram "pending" e o
 *    job esgotou as tentativas ou nunca correu).
 *
 * O job é idempotente (não reenvia "sent"), por isso repetir este comando é seguro.
 */
class LeadsRetry extends Command
{
    protected $signature = 'leads:retry
        {--pending : Incluir também as pending paradas há mais de 1 hora}
        {--id=* : Reenviar apenas estas leads (por id)}
        {--dry-run : Mostrar o que seria reenviado, sem despachar}';

    protected $description = 'Volta a colocar na queue as leads falhadas (e, opcionalmente, as pending paradas)';

    public function handle(): int
    {
        if (blank(config('casafari.token'))) {
            $this->components->warn('CASAFARI_TOKEN não está definido — os jobs vão falhar de novo. A continuar na mesma (ficam em retry).');
        }

        $query = Lead::query()->orderBy('id');

        if ($ids = array_filter((array) $this->option('id'))) {
            $query->whereIn('id', $ids)->where('crm_status', '!=', LeadStatus::Sent);
        } else {
            $query->where(function ($q) {
                $q->where('crm_status', LeadStatus::Failed);
                if ($this->option('pending')) {
                    // Pending "parada": criada há mais de 1 h e sem tentativas recentes.
                    $q->orWhere(fn ($w) => $w->where('crm_status', LeadStatus::Pending)
                        ->where('created_at', '<', Carbon::now()->subHour()));
                }
            });
        }

        $leads = $query->get();

        if ($leads->isEmpty()) {
            $this->components->info('Nada para reenviar.');

            return self::SUCCESS;
        }

        foreach ($leads as $lead) {
            $this->components->twoColumnDetail(
                "Lead #{$lead->id} — {$lead->name} ({$lead->source->value}, {$lead->crm_status->value}, {$lead->attempts} tentativas)",
                $this->option('dry-run') ? 'dry-run' : 'na queue'
            );

            if (! $this->option('dry-run')) {
                // Volta a pending para o estado refletir a nova ronda de envio.
                $lead->forceFill(['crm_status' => LeadStatus::Pending, 'last_error' => null])->save();
                SendLeadToCasafari::dispatch($lead->id);
            }
        }

        $this->components->info(sprintf('%d lead(s) %s.', $leads->count(), $this->option('dry-run') ? 'por reenviar (dry-run)' : 'recolocadas na queue'));

        return self::SUCCESS;
    }
}
