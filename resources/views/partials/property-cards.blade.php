{{-- Só os cartões, sem grelha: quem pede decide a moldura (vistos recentemente). --}}
@foreach ($properties as $property)
    <x-property.card :property="$property" />
@endforeach
