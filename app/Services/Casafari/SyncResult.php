<?php

namespace App\Services\Casafari;

/**
 * Resultado de uma execução do sync — o que o comando imprime, o que o evento
 * transporta e o que os testes verificam.
 */
final class SyncResult
{
    public int $seen = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $deactivated = 0;

    public int $errors = 0;

    /** true quando a desativação foi saltada por haver erros de mapeamento. */
    public bool $deactivationSkipped = false;

    /** @var array<int, string> mensagens de erro (limitadas) para o output */
    public array $errorMessages = [];

    public float $seconds = 0.0;

    public function __construct(public readonly bool $dryRun, public readonly bool $force) {}

    public function addError(string $message): void
    {
        $this->errors++;
        if (count($this->errorMessages) < 20) {
            $this->errorMessages[] = $message;
        }
    }

    /** @return array<string, int|bool|float> */
    public function toArray(): array
    {
        return [
            'seen' => $this->seen,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'deactivated' => $this->deactivated,
            'errors' => $this->errors,
            'deactivation_skipped' => $this->deactivationSkipped,
            'dry_run' => $this->dryRun,
            'force' => $this->force,
            'seconds' => round($this->seconds, 2),
        ];
    }
}
