<x-layouts.app :title="__('ui.errors.500_title')" robots="noindex,nofollow">
    <section class="container-site pt-24 pb-16">
        <p class="label">500</p>
        <h1 class="mt-3 max-w-2xl text-4xl sm:text-5xl">{{ __('ui.errors.500_title') }}</h1>
        <p class="mt-6 max-w-xl text-ink-muted">{{ __('ui.errors.500_lead') }}</p>
        <a href="{{ route('home') }}" class="link mt-10 inline-block text-sm">{{ __('ui.errors.404_back') }}</a>
    </section>
</x-layouts.app>
