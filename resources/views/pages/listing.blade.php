{{--
    Listagem (/comprar, /arrendar). O canonical ignora a query string dos filtros;
    páginas filtradas continuam indexáveis via links internos (zonas) mas apontam
    para a listagem base como canónica, evitando conteúdo duplicado.
--}}
<x-layouts.app :title="$title" :description="$description" :canonical="url()->current()">
    <div class="pt-12">
        <livewire:property-listing :business-type="$businessType->value" />
    </div>
</x-layouts.app>
