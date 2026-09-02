{{--
    "Vistos recentemente": os slugs vivem no localStorage ($store.recent, em
    resources/js/app.js) — sem contas nem cookies. O browser pede os cartões
    ao servidor (property.cards) e a secção só aparece quando há o que mostrar.
    O x-html do Alpine inicializa os cartões injetados (favoritos incluídos).
--}}
@props(['exclude' => null])
<section x-cloak x-show="html !== ''"
         x-data="{
             html: '',
             async init() {
                 const slugs = (this.$store.recent?.slugs ?? []).filter((s) => s !== @js($exclude)).slice(0, 6);
                 if (! slugs.length) return;
                 try {
                     const r = await fetch(@js(route('property.cards')) + '?slugs=' + encodeURIComponent(slugs.join(',')), { headers: { Accept: 'text/html' } });
                     if (! r.ok) return;
                     const t = (await r.text()).trim();
                     if (t) this.html = t;
                 } catch {}
             },
         }"
         {{ $attributes }}>
    <h2 class="text-3xl">{{ __('ui.property.recent') }}</h2>
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" x-html="html"></div>
</section>
