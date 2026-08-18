<?php

namespace App\Services\Casafari;

use Generator;
use RuntimeException;
use XMLReader;

/**
 * Lê o feed em streaming com XMLReader e devolve, um de cada vez, o XML bruto
 * de cada nó de imóvel. Nunca carrega o ficheiro inteiro em memória — com
 * milhares de imóveis e dezenas de fotos cada, simplexml rebentaria.
 *
 * Devolve o XML BRUTO (string) e não um objeto: é sobre esse texto que se
 * calcula o sha256 usado para saltar imóveis inalterados.
 */
class FeedReader
{
    /**
     * @return Generator<int, string> XML bruto de cada nó item_node
     */
    public function items(string $path): Generator
    {
        if (! is_file($path)) {
            throw new RuntimeException("Ficheiro do feed não encontrado: {$path}");
        }

        $itemNode = (string) config('casafari.feed.item_node');

        $reader = new XMLReader;

        // LIBXML_NONET: sem acesso à rede (entidades externas). O feed é uma fonte externa.
        if (! $reader->open($path, null, LIBXML_NONET | LIBXML_NOCDATA)) {
            throw new RuntimeException("Não foi possível abrir o feed: {$path}");
        }

        try {
            // Avança até ao primeiro nó de imóvel.
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === $itemNode) {
                    break;
                }
            }

            // A partir daí, salta de irmão em irmão sem expandir o resto do documento.
            while ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === $itemNode) {
                $xml = $reader->readOuterXml();

                if ($xml !== '') {
                    yield $xml;
                }

                if (! $reader->next($itemNode)) {
                    break;
                }
            }
        } finally {
            $reader->close();
        }
    }
}
