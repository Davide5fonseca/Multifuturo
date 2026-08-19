<?php

namespace App\Console\Commands;

use App\Models\Zone;
use App\Support\PropertyCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * zones:import — carrega o conteúdo editorial das páginas de zona a partir de
 * ficheiros Markdown em database/content/zones/.
 *
 * Formato de cada ficheiro (ex.: cascais.md, cascais--estoril.md):
 *
 *   ---
 *   city_slug: cascais            (obrigatório; slug ASCII do concelho)
 *   locality_slug: estoril        (opcional; ausente = página do concelho)
 *   title: Viver em Cascais       (opcional)
 *   meta_description: …           (opcional, ≤ 200 caracteres)
 *   cover_url: /images/zonas/cascais.jpg   (opcional)
 *   published: true               (opcional; default true)
 *   ---
 *   Primeiro parágrafo = intro (aparece no topo da página).
 *
 *   Restantes parágrafos = body, separados por linhas em branco.
 *
 * Upsert por (city_slug, locality_slug): reimportar é seguro; o que não está
 * em ficheiro não é tocado. Invalida a cache das zonas no fim.
 */
class ZonesImport extends Command
{
    protected $signature = 'zones:import
        {--path= : Pasta com os .md (default: database/content/zones)}
        {--prune : Despublicar zonas da BD sem ficheiro correspondente}';

    protected $description = 'Importa o conteúdo editorial das zonas a partir de ficheiros Markdown';

    public function handle(): int
    {
        $dir = $this->option('path') ?: database_path('content/zones');

        if (! File::isDirectory($dir)) {
            $this->components->error("Pasta não encontrada: {$dir}");

            return self::FAILURE;
        }

        $files = collect(File::files($dir))->filter(fn ($f) => $f->getExtension() === 'md');

        if ($files->isEmpty()) {
            $this->components->warn("Sem ficheiros .md em {$dir}. Vê o exemplo em database/content/zones/_exemplo.md.dist.");

            return self::SUCCESS;
        }

        $seen = [];
        $errors = 0;

        foreach ($files as $file) {
            try {
                [$meta, $intro, $body] = $this->parse($file->getContents());

                if (blank($meta['city_slug'] ?? null)) {
                    throw new \InvalidArgumentException('falta city_slug no front matter');
                }

                $citySlug = Str::slug($meta['city_slug']);
                $localitySlug = filled($meta['locality_slug'] ?? null) ? Str::slug($meta['locality_slug']) : null;

                Zone::query()->updateOrCreate(
                    ['city_slug' => $citySlug, 'locality_slug' => $localitySlug],
                    [
                        'title' => $meta['title'] ?? null,
                        'meta_description' => isset($meta['meta_description']) ? Str::limit($meta['meta_description'], 197) : null,
                        'intro' => $intro,
                        'body' => $body,
                        'cover_url' => $meta['cover_url'] ?? null,
                        'is_published' => filter_var($meta['published'] ?? 'true', FILTER_VALIDATE_BOOL),
                    ]
                );

                $seen[] = $citySlug.'|'.($localitySlug ?? '');
                $this->components->twoColumnDetail($file->getFilename(), $citySlug.($localitySlug ? " / {$localitySlug}" : ''));
            } catch (\Throwable $e) {
                $errors++;
                $this->components->error("{$file->getFilename()}: {$e->getMessage()}");
            }
        }

        if ($this->option('prune')) {
            $pruned = Zone::query()->get()
                ->filter(fn ($z) => ! in_array($z->city_slug.'|'.($z->locality_slug ?? ''), $seen, true))
                ->each(fn ($z) => $z->update(['is_published' => false]))
                ->count();
            $this->components->twoColumnDetail('Despublicadas (sem ficheiro)', (string) $pruned);
        }

        PropertyCache::flush();
        $this->components->info(sprintf('%d zona(s) importadas, %d erro(s). Cache limpa.', count($seen), $errors));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Front matter simples (chave: valor) + intro (1.º parágrafo) + body (resto).
     *
     * @return array{0: array<string, string>, 1: ?string, 2: ?string}
     */
    private function parse(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", trim($raw));
        $meta = [];
        $content = $raw;

        if (str_starts_with($raw, '---')) {
            [$fm, $content] = array_pad(preg_split('/^---\s*$/m', substr($raw, 3), 2), 2, '');
            foreach (preg_split('/\n/', trim($fm)) as $line) {
                if (preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $m)) {
                    $meta[$m[1]] = trim($m[2], " \t\"'");
                }
            }
        }

        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', trim($content)))));
        $intro = $paragraphs[0] ?? null;
        $body = count($paragraphs) > 1 ? implode("\n\n", array_slice($paragraphs, 1)) : null;

        return [$meta, $intro, $body];
    }
}
