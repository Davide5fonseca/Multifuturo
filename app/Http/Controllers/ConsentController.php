<?php

namespace App\Http\Controllers;

use App\Models\ConsentLog;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Regista cada escolha feita no aviso de cookies (ver ConsentLog). O
 * browser chama isto logo depois de gravar o cookie mf_consent; se a
 * chamada falhar, a escolha continua a valer — o cookie é a fonte de
 * verdade, o registo é a prova.
 */
class ConsentController extends Controller
{
    public function store(Request $request): Response
    {
        $categories = (array) config('consent.categories');

        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1', 'max:65535'],
            'action' => ['required', 'in:accept_all,reject_all,custom'],
            'choices' => ['required', 'array:'.implode(',', $categories)],
            ...array_combine(
                array_map(fn ($c) => "choices.{$c}", $categories),
                array_fill(0, count($categories), ['required', 'boolean']),
            ),
        ]);

        ConsentLog::query()->create([
            'version' => (int) $data['version'],
            'action' => $data['action'],
            'choices' => array_map(fn ($v) => (bool) $v, $data['choices']),
            'locale' => app()->getLocale(),
            'ip_hash' => Lead::hashIp($request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        return response()->noContent();
    }
}
