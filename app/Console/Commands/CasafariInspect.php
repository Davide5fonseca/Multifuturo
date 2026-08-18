<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use XMLReader;

/**
 * Passo 0 — Inspeção do feed XML do CASAFARI (Feedcruncher).
 *
 * Descarrega o feed, guarda uma amostra em storage/app/casafari/sample.xml e
 * imprime o que precisamos para desenhar o mapper com base no ficheiro REAL:
 *  - hierarquia dos nós (com contagens);
 *  - estrutura completa do primeiro imóvel;
 *  - contagem total de imóveis;
 *  - se o idioma das traduções vem como atributo (lang="pt") ou como elemento.
 *
 * Não escreve na base de dados. Não faz mapeamento. É só diagnóstico.
 */
class CasafariInspect extends Command
{
    protected $signature = 'casafari:inspect
        {--file= : Inspecionar um XML local em vez de descarregar (caminho absoluto ou relativo ao projeto)}
        {--depth=6 : Profundidade máxima da árvore de hierarquia}
        {--first-depth=8 : Profundidade máxima ao imprimir o primeiro imóvel}';

    protected $description = 'Descarrega o feed XML do CASAFARI, guarda uma amostra e imprime a sua estrutura';

    /** Nomes de nó que, tipicamente, representam um imóvel individual. */
    private const CANDIDATE_ITEM_NAMES = ['property', 'imovel', 'imóvel', 'listing', 'item', 'anuncio', 'anúncio', 'ad'];

    public function handle(): int
    {
        $path = $this->option('file')
            ? $this->resolveLocalFile($this->option('file'))
            : $this->downloadFeed();

        if ($path === null) {
            return self::FAILURE;
        }

        $this->line('');
        $this->components->twoColumnDetail('Ficheiro', $path);
        $this->components->twoColumnDetail('Tamanho', $this->humanSize(filesize($path)));

        // 1) Hierarquia de nós, com contagens por caminho.
        [$tree, $langInfo] = $this->scanHierarchy($path, (int) $this->option('depth'));

        $this->line('');
        $this->components->info('Hierarquia dos nós (caminho → ocorrências)');
        foreach ($tree as $nodePath => $count) {
            $depth = substr_count($nodePath, '/');
            $name = basename($nodePath);
            $this->line(str_repeat('  ', $depth).$name.'  <fg=gray>×'.number_format($count).'</>');
        }

        // 2) Nó que representa um imóvel e contagem total.
        $itemPath = $this->guessItemPath($tree);

        $this->line('');
        if ($itemPath === null) {
            $this->components->warn('Não consegui identificar o nó de imóvel automaticamente. Vê a hierarquia acima.');
        } else {
            $this->components->twoColumnDetail('Nó de imóvel (estimado)', $itemPath);
            $this->components->twoColumnDetail('Total de imóveis', number_format($tree[$itemPath]));

            // 3) Estrutura completa do primeiro imóvel.
            $this->line('');
            $this->components->info('Estrutura do primeiro imóvel');
            $this->printFirstItem($path, basename($itemPath), (int) $this->option('first-depth'));
        }

        // 4) Idioma nas traduções: atributo ou elemento?
        $this->line('');
        $this->components->info('Idioma das traduções');
        if ($langInfo['attribute'] === 0 && $langInfo['element'] === 0) {
            $this->line('  Não encontrei nem atributos nem elementos com nome "lang"/"language"/"locale".');
        } else {
            $this->components->twoColumnDetail('Como atributo (ex.: <Title lang="pt">)', number_format($langInfo['attribute']).' ocorrências');
            $this->components->twoColumnDetail('Como elemento (ex.: <Lang>pt</Lang>)', number_format($langInfo['element']).' ocorrências');
            if ($langInfo['values'] !== []) {
                $this->components->twoColumnDetail('Valores encontrados', implode(', ', array_keys($langInfo['values'])));
            }
        }

        $this->line('');
        $this->components->info('Fotos');
        $this->components->twoColumnDetail('Total de URLs de imagem', number_format($langInfo['photos']));
        if ($itemPath !== null && $tree[$itemPath] > 0) {
            $this->components->twoColumnDetail('Média por imóvel', number_format($langInfo['photos'] / $tree[$itemPath], 1));
        }

        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Faz GET ao feed com timeout e retries, e grava a amostra em storage.
     */
    private function downloadFeed(): ?string
    {
        $url = config('casafari.feed_url');

        if (blank($url)) {
            $this->components->error('CASAFARI_FEED_URL não está definido no .env. Sem URL não há feed para inspecionar.');
            $this->line('  Alternativa: casafari:inspect --file=caminho/para/feed.xml');

            return null;
        }

        $this->components->task('A descarregar o feed', function () use ($url, &$body): void {
            $body = Http::timeout((int) config('casafari.feed_timeout'))
                ->retry((int) config('casafari.feed_retries'), (int) config('casafari.feed_retry_delay_ms'))
                ->get($url)
                ->throw()
                ->body();
        });

        $disk = Storage::disk('local');
        $relative = config('casafari.storage_dir').'/sample.xml';
        $disk->put($relative, $body);

        return $disk->path($relative);
    }

    private function resolveLocalFile(string $file): ?string
    {
        $candidates = [$file, base_path($file), Storage::disk('local')->path($file)];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return realpath($candidate);
            }
        }

        $this->components->error("Ficheiro não encontrado: {$file}");

        return null;
    }

    /**
     * Percorre o XML em streaming (XMLReader) e devolve:
     *  - array caminho => ocorrências, na ordem de primeira aparição;
     *  - estatísticas sobre "lang" (atributo vs elemento) e URLs de imagem.
     *
     * @return array{0: array<string,int>, 1: array{attribute:int, element:int, values:array<string,int>, photos:int}}
     */
    private function scanHierarchy(string $path, int $maxDepth): array
    {
        $reader = new XMLReader;
        $reader->open($path, null, LIBXML_NONET); // sem acesso à rede (entidades externas)

        $tree = [];
        $stack = [];
        $lang = ['attribute' => 0, 'element' => 0, 'values' => [], 'photos' => 0];
        $langNames = ['lang', 'language', 'locale', 'culture'];

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                $stack[$reader->depth] = $reader->localName;
                $nodePath = implode('/', array_slice($stack, 0, $reader->depth + 1));

                if ($reader->depth <= $maxDepth) {
                    $tree[$nodePath] = ($tree[$nodePath] ?? 0) + 1;
                }

                // Atributo lang="…"?
                if ($reader->hasAttributes) {
                    foreach ($langNames as $attr) {
                        $value = $reader->getAttribute($attr);
                        if ($value !== null) {
                            $lang['attribute']++;
                            $lang['values'][$value] = ($lang['values'][$value] ?? 0) + 1;
                        }
                    }
                }

                $lower = strtolower($reader->localName);

                // Elemento <Lang>…</Lang>?
                if (in_array($lower, $langNames, true) && ! $reader->isEmptyElement) {
                    $value = trim((string) $reader->readString());
                    if ($value !== '') {
                        $lang['element']++;
                        $lang['values'][$value] = ($lang['values'][$value] ?? 0) + 1;
                    }
                }

                // Contagem grosseira de imagens: elementos cujo texto é um URL de imagem.
                if (! $reader->isEmptyElement && (str_contains($lower, 'photo') || str_contains($lower, 'image') || str_contains($lower, 'picture'))) {
                    $inner = $reader->readInnerXml();
                    $text = trim(html_entity_decode($inner));
                    // Só elementos-folha (sem filhos): evita contar o contentor <Photos> como uma foto.
                    if (! str_contains($inner, '<') && preg_match('~^https?://.+\.(jpe?g|png|webp|gif)(\?.*)?$~i', $text)) {
                        $lang['photos']++;
                    }
                }
            }
        }

        $reader->close();

        return [$tree, $lang];
    }

    /**
     * Estima qual é o nó que representa um imóvel: o caminho com mais ocorrências
     * entre os nomes candidatos, ou — em alternativa — o nó repetido mais
     * superficial na árvore.
     */
    private function guessItemPath(array $tree): ?string
    {
        $best = null;

        foreach ($tree as $nodePath => $count) {
            if (in_array(strtolower(basename($nodePath)), self::CANDIDATE_ITEM_NAMES, true)) {
                if ($best === null || $count > $tree[$best]) {
                    $best = $nodePath;
                }
            }
        }

        if ($best !== null) {
            return $best;
        }

        // Fallback: primeiro nó com mais de uma ocorrência abaixo da raiz.
        foreach ($tree as $nodePath => $count) {
            if ($count > 1 && substr_count($nodePath, '/') >= 1) {
                return $nodePath;
            }
        }

        return null;
    }

    /**
     * Imprime o primeiro nó $itemName com todos os descendentes, atributos e
     * valores (truncados) — é isto que alimenta o mapper.
     */
    private function printFirstItem(string $path, string $itemName, int $maxDepth): void
    {
        $reader = new XMLReader;
        $reader->open($path, null, LIBXML_NONET);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === $itemName) {
                $xml = $reader->readOuterXml();
                break;
            }
        }
        $reader->close();

        if (empty($xml)) {
            $this->components->warn("Não encontrei nenhum <{$itemName}>.");

            return;
        }

        $doc = new \DOMDocument;
        $doc->loadXML($xml, LIBXML_NONET);
        $this->printDomNode($doc->documentElement, 0, $maxDepth);
    }

    private function printDomNode(\DOMElement $el, int $depth, int $maxDepth): void
    {
        $indent = str_repeat('  ', $depth);
        $attrs = '';
        foreach ($el->attributes ?? [] as $attr) {
            $attrs .= ' <fg=yellow>'.$attr->name.'</>="'.$this->truncate($attr->value, 40).'"';
        }

        $children = array_filter(iterator_to_array($el->childNodes), fn ($n) => $n instanceof \DOMElement);

        if ($children === []) {
            $text = trim($el->textContent);
            $this->line($indent.'<fg=cyan>'.$el->localName.'</>'.$attrs.($text !== '' ? ' = <fg=gray>'.$this->truncate($text, 90).'</>' : ' <fg=gray>(vazio)</>'));

            return;
        }

        $this->line($indent.'<fg=cyan>'.$el->localName.'</>'.$attrs.'  <fg=gray>('.count($children).' filhos)</>');

        if ($depth >= $maxDepth) {
            $this->line($indent.'  <fg=gray>…</>');

            return;
        }

        // Filhos repetidos com o mesmo nome (ex.: 20 <Photo>) — mostra os dois primeiros e resume o resto.
        $seen = [];
        foreach ($children as $child) {
            $seen[$child->localName] = ($seen[$child->localName] ?? 0) + 1;
            if ($seen[$child->localName] > 2) {
                continue;
            }
            $this->printDomNode($child, $depth + 1, $maxDepth);
        }
        foreach ($seen as $name => $n) {
            if ($n > 2) {
                $this->line($indent.'  <fg=gray>… mais '.($n - 2).' <'.$name.'></>');
            }
        }
    }

    private function truncate(string $value, int $length): string
    {
        $value = preg_replace('/\s+/', ' ', $value);

        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1).'…' : $value;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
