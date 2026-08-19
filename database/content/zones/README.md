# Conteúdo editorial das zonas

Um ficheiro `.md` por página de zona. Copie `_exemplo.md.dist` para, p. ex.:

- `cascais.md` — página do concelho (`/zonas/cascais`)
- `cascais--estoril.md` — página de freguesia (`/zonas/cascais/estoril`; o nome do
  ficheiro é livre — o que conta é o front matter `city_slug` + `locality_slug`)

Importar com: `.\sail.ps1 artisan zones:import` (upsert; reimportar é seguro).
`--prune` despublica zonas da BD que já não têm ficheiro.

O primeiro parágrafo é a **intro** (topo da página); os restantes são o corpo.
Sem ficheiro, a página de zona existe na mesma — só sem texto editorial.
