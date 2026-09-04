<x-mail::message>
# 🎂 ¡Feliz Cumpleaños, {{ $user->name }}! 🎉

En este día tan especial, todo el equipo de **{{ config('syndicate.name') }} ({{ config('syndicate.acronym') }})** queremos hacerte llegar nuestras más sinceras felicitaciones y mejores deseos.

<x-mail::panel>
"{{ $messageContent }}"
</x-mail::panel>

@if(!empty($greetingTags))
**Nuestros mejores deseos para ti en este nuevo ciclo:**

@foreach($greetingTags as $tag)
`{{ $tag }}` &nbsp;
@endforeach
@endif

<br>

Gracias por formar parte de nuestra comunidad sindical. Que este nuevo año de vida esté colmado de salud, bendiciones, metas cumplidas y momentos memorables junto a quienes más quieres.

<x-mail::button :url="config('app.url')">
Ir al Portal Sindical
</x-mail::button>

Con aprecio y fraternidad,<br>
**{{ config('syndicate.name') }} ({{ config('syndicate.acronym') }})**<br>
_{{ config('syndicate.slogan', 'En unidad permanente, TODOS SOMOS LA FUERZA') }}_
</x-mail::message>
