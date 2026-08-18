<?php

namespace App\Services\Casafari;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Descarrega o feed XML do CASAFARI para um ficheiro em storage/app/casafari/.
 *
 * Grava em disco em vez de devolver o corpo em memória: o feed é depois lido
 * em streaming pelo FeedReader, e fica uma cópia do último feed para diagnóstico.
 */
class FeedClient
{
    public const LATEST_FILE = 'latest.xml';

    /**
     * @return string caminho absoluto do ficheiro descarregado
     *
     * @throws RuntimeException se não houver URL configurado
     * @throws RequestException se o pedido falhar depois dos retries
     */
    public function download(): string
    {
        $url = (string) config('casafari.feed_url');

        if ($url === '') {
            throw new RuntimeException('CASAFARI_FEED_URL não está definido.');
        }

        $disk = Storage::disk('local');
        $relative = config('casafari.storage_dir').'/'.self::LATEST_FILE;
        $disk->makeDirectory(config('casafari.storage_dir'));
        $path = $disk->path($relative);

        // Escreve para um temporário e só substitui o latest.xml se o pedido correu bem —
        // um download interrompido não pode passar por "feed vazio".
        $tmp = $path.'.part';

        $response = Http::timeout((int) config('casafari.feed_timeout'))
            ->retry((int) config('casafari.feed_retries'), (int) config('casafari.feed_retry_delay_ms'))
            ->accept('application/xml')
            ->withOptions(['stream' => true]) // corpo lido em blocos, não carregado em memória
            ->get($url)
            ->throw();

        $body = $response->toPsrResponse()->getBody();
        $out = fopen($tmp, 'wb');
        try {
            while (! $body->eof()) {
                fwrite($out, $body->read(1024 * 1024));
            }
        } finally {
            fclose($out);
            $body->close();
        }

        if (! is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            throw new RuntimeException('O feed foi descarregado mas está vazio (0 bytes).');
        }

        rename($tmp, $path);

        return $path;
    }
}
