{{--
    Simulador "Quanto vale a minha casa?" — estimativa imediata.

    Vive dentro do formulário de pedido de avaliação (passo 1). Os €/m² vêm
    de App\Support\Valuation: valores de referência por concelho e freguesia
    (escritos no backoffice ou importados do INE) ou, na falta deles, a
    mediana das nossas vendas publicadas. A tabela inteira vem embutida na
    página e a conta faz-se no browser: nada sai para o servidor até a pessoa
    enviar o pedido — e aí seguem os mesmos dados e a estimativa que ela viu
    (evento 'valuation-change', ouvido pelo formulário).

    Concelho e freguesia escrevem-se livremente (com sugestões); a
    correspondência ignora maiúsculas e acentos. A freguesia só conta quando
    tem valor próprio para o tipo — senão usa-se o concelho; sem concelho,
    o valor por omissão da agência (entrada '*' da tabela), se existir.
    Nos terrenos o estado de conservação não entra na conta.

        valor = €/m² × área × fator do estado, ±10 %, arredondado ao milhar
--}}
@props(['table'])

@php
    $default = \App\Support\Valuation::DEFAULT_CITY;
    $cities = array_values(array_filter(array_keys($table), fn ($c) => $c !== $default));
    $typeLabels = collect(\App\Support\Valuation::TYPES)->mapWithKeys(fn ($t) => [$t => __('ui.valuation.type_'.$t)])->all();
    $conditionLabels = collect(array_keys(\App\Support\Valuation::CONDITIONS))->mapWithKeys(fn ($c) => [$c => __('ui.valuation.condition_'.$c)])->all();
    $intl = app()->getLocale() === 'en' ? 'en-IE' : 'pt-PT';
@endphp

@if (empty($table))
    <p class="rounded-xl bg-sand-50 px-5 py-4 text-sm text-ink-muted ring-1 ring-sand-200">{{ __('ui.valuation.unavailable') }}</p>
@else
<div
    x-data="{
        table: @js($table),
        types: @js($typeLabels),
        conditions: @js($conditionLabels),
        factors: @js(\App\Support\Valuation::CONDITIONS),
        basisText: { portfolio: @js(__('ui.valuation.basis_portfolio')), reference: @js(__('ui.valuation.basis_reference')), ine: @js(__('ui.valuation.basis_ine')), default: @js(__('ui.valuation.basis_default')) },
        cities: @js($cities),
        defaults: @js($table[$default]['types'] ?? []),
        margin: {{ \App\Support\Valuation::MARGIN }},
        city: '', locality: '', type: 'apartment', area: null, condition: 'good',

        // 'Sintra', 'sintra' e 'SINTRA' são o mesmo concelho; 'Águeda' e 'agueda' também.
        fold(s) { return String(s ?? '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); },
        // Uma freguesia agregada (União das freguesias de Queluz e Belas) também se encontra por qualquer uma das partes.
        // Atenção: este bloco vive dentro de um atributo HTML — nada de aspas duplas aqui.
        parts(name) { return this.fold(name).replace(/^uniao d(as|e) freguesias d[eao]s? /, '').split(/, | e /); },
        match(list, wanted) {
            const w = this.fold(wanted);
            if (!w) return null;
            return list.find(x => this.fold(x) === w) ?? list.find(x => this.parts(x).includes(w)) ?? null;
        },
        get cityKey() { return this.match(this.cities, this.city); },
        get entry() { return this.cityKey ? this.table[this.cityKey] : null; },
        get localities() { return this.entry ? Object.keys(this.entry.localities) : []; },
        get localityKey() { return this.match(this.localities, this.locality); },
        // Freguesia com valor próprio para o tipo; senão o concelho; senão o valor por omissão da agência.
        get resolved() {
            if (this.city.trim() === '') return null;
            const local = this.entry && this.localityKey ? this.entry.localities[this.localityKey][this.type] : null;
            if (local) return { base: local, place: this.localityKey + ', ' + this.cityKey };
            const city = this.entry ? this.entry.types[this.type] : null;
            if (city) return { base: city, place: this.cityKey };
            const def = this.defaults[this.type];
            return def ? { base: def, place: '' } : null;
        },
        get base() { return this.resolved?.base ?? null; },
        hasType(t) { return !!(this.defaults[t] || (this.entry && (this.entry.types[t] || (this.localityKey && this.entry.localities[this.localityKey][t])))); },
        get ready() { return this.base !== null && this.area > 0; },
        get factor() { return this.type === 'land' ? 1 : this.factors[this.condition]; },
        get mid() { return this.ready ? this.base.ppm2 * this.area * this.factor : 0; },
        get noDataText() { return this.type === 'land' ? @js(__('ui.valuation.no_data_land')) : @js(__('ui.valuation.no_data')); },
        get min() { return this.thousands(this.mid * (1 - this.margin)); },
        get max() { return this.thousands(this.mid * (1 + this.margin)); },
        get range() { return this.eur(this.min) + ' – ' + this.eur(this.max); },
        get basis() {
            if (!this.resolved) return '';
            return (this.basisText[this.base.source] ?? '').replace(':n', this.base.n).replace(':place', this.resolved.place);
        },

        thousands(v) { return Math.round(v / 1000) * 1000; },
        eur(v) { return new Intl.NumberFormat('{{ $intl }}', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0, useGrouping: 'always' }).format(v); },
        int(v) { return new Intl.NumberFormat('{{ $intl }}', { maximumFractionDigits: 0, useGrouping: 'always' }).format(v); },
        // Ao mudar de sítio, um tipo sem valor lá salta para o primeiro que tenha.
        syncType() { if (this.city.trim() !== '' && !this.hasType(this.type)) { const t = Object.keys(this.types).find(t => this.hasType(t)); if (t) this.type = t; } },
        // O que segue para o formulário (campos escondidos do pedido).
        get detail() {
            return {
                city: this.cityKey ?? this.city.trim(), locality: this.localityKey ?? this.locality.trim(),
                type: this.types[this.type], area: this.area,
                condition: this.type === 'land' ? '' : this.conditions[this.condition], estimate: this.ready ? this.range : '',
                message: this.ready ? @js(__('ui.valuation.message')).replace(':estimate', this.range) : '',
            };
        },
        emit() { window.dispatchEvent(new CustomEvent('valuation-change', { detail: this.detail })); },
    }"
    x-effect="emit()"
    data-valuation
>
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="val-city" class="label">{{ __('ui.valuation.city') }}</label>
            <input id="val-city" type="text" list="val-cities" autocomplete="off" x-model="city" @input="locality = ''; syncType()"
                   placeholder="{{ __('ui.valuation.pick_city') }}" class="field mt-2">
            {{-- Sugestões: os concelhos com valores. Escrever outro qualquer é permitido. --}}
            <datalist id="val-cities">
                @foreach ($cities as $city)
                    <option value="{{ $city }}">
                @endforeach
            </datalist>
        </div>
        <div x-show="localities.length > 0">
            <label for="val-locality" class="label">{{ __('ui.valuation.locality') }} <span class="normal-case tracking-normal">({{ __('ui.valuation.optional') }})</span></label>
            <input id="val-locality" type="text" list="val-localities" autocomplete="off" x-model="locality" @input="syncType()"
                   placeholder="{{ __('ui.valuation.pick_locality') }}" class="field mt-2">
            <datalist id="val-localities">
                <template x-for="l in localities" :key="l"><option :value="l"></template>
            </datalist>
        </div>
        <div>
            <label for="val-type" class="label">{{ __('ui.valuation.type') }}</label>
            <select id="val-type" x-model="type" class="field mt-2">
                @foreach ($typeLabels as $key => $label)
                    <option value="{{ $key }}" :disabled="city.trim() !== '' && !hasType('{{ $key }}')">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="val-area" class="label">{{ __('ui.valuation.area') }}</label>
            <input id="val-area" type="number" min="10" max="100000" step="1" inputmode="numeric" x-model.number="area" class="field mt-2" aria-describedby="val-area-hint">
            <p id="val-area-hint" class="mt-1 text-xs text-ink-muted">{{ __('ui.valuation.area_hint') }}</p>
        </div>
        {{-- Num terreno o estado de conservação não se aplica. --}}
        <div x-show="type !== 'land'">
            <label for="val-condition" class="label">{{ __('ui.valuation.condition') }}</label>
            <select id="val-condition" x-model="condition" class="field mt-2">
                @foreach ($conditionLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-5" aria-live="polite">
        <template x-if="ready">
            <div class="rounded-xl bg-sand-50 p-6 ring-1 ring-sand-200">
                <p class="label">{{ __('ui.valuation.result') }}</p>
                <p class="price mt-2 text-3xl sm:text-4xl" x-text="range"></p>
                <p class="mt-2 text-sm text-ink-muted"><span x-text="'≈ ' + int(base.ppm2) + ' €/m²'"></span> · <span x-text="basis"></span></p>
            </div>
        </template>
        <template x-if="city.trim() !== '' && !base">
            <p class="rounded-xl bg-sand-50 px-5 py-4 text-sm ring-1 ring-sand-200" x-text="noDataText"></p>
        </template>
    </div>

    <p class="mt-4 text-xs leading-relaxed text-ink-muted">{{ __('ui.valuation.note') }}</p>
</div>
@endif
