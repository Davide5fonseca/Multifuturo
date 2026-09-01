<?php

namespace App\Services;

use App\Models\MfaCode;
use App\Models\User;
use App\Notifications\MfaCodeNotification;
use Illuminate\Support\Facades\Hash;

/**
 * Verificação em duas etapas por email: gera o código, guarda o hash,
 * envia-o e valida-o. As regras (validade, tentativas, intervalo de reenvio)
 * estão em config/portal.php.
 */
class MfaService
{
    public const OK = 'ok';

    public const WRONG = 'wrong';

    public const EXPIRED = 'expired';

    public const TOO_MANY = 'too_many';

    /** Emite um código novo (invalidando os anteriores) e envia-o por email, na hora. */
    public function send(User $user): void
    {
        MfaCode::query()->where('user_id', $user->id)->whereNull('used_at')->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        MfaCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('portal.code_minutes')),
            'created_at' => now(),
        ]);

        // Não vai para a fila: o código tem de chegar durante o login.
        $user->notifyNow(new MfaCodeNotification($code, (int) config('portal.code_minutes')));
    }

    /** Valida o código; queima-o no sucesso e ao exceder as tentativas. */
    public function verify(User $user, string $code): string
    {
        $record = MfaCode::query()->where('user_id', $user->id)->live()->latest('id')->first();

        if (! $record) {
            return self::EXPIRED;
        }

        $record->increment('attempts');

        if ($record->attempts > (int) config('portal.max_attempts')) {
            $record->update(['used_at' => now()]);

            return self::TOO_MANY;
        }

        if (! Hash::check($code, $record->code_hash)) {
            return self::WRONG;
        }

        $record->update(['used_at' => now()]);

        return self::OK;
    }

    /** Segundos até se poder pedir novo código; null se já se pode. */
    public function secondsUntilResend(User $user): ?int
    {
        $last = MfaCode::query()->where('user_id', $user->id)->latest('id')->first();

        if (! $last) {
            return null;
        }

        $elapsed = (int) $last->created_at->diffInSeconds(now());
        $cooldown = (int) config('portal.resend_seconds');

        return $elapsed < $cooldown ? $cooldown - $elapsed : null;
    }
}
