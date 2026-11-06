<x-mail::message>
# ¡Feliz Cumpleaños, {{ $user->name }}!

En este día tan especial, quienes integramos el **{{ config('syndicate.name') }} ({{ config('syndicate.acronym') }})** nos unimos para hacerle llegar nuestras más sinceras felicitaciones y mejores augurios.

<x-mail::panel>
*«{{ $messageContent }}»*
</x-mail::panel>

@if(!empty($greetingTags))
**Deseos para este nuevo ciclo:**  
{{ implode(' • ', $greetingTags) }}
@endif

Agradecemos profundamente su valiosa labor y compromiso diario como parte fundamental de nuestra comunidad gremial. Que este nuevo año de vida le depare plena salud, bienestar, metas consolidadas y gratos momentos junto a su apreciable familia.

<x-mail::button :url="config('app.url')">
Acceder al Portal Sindical
</x-mail::button>

Fraternalmente,<br>
**Comité Ejecutivo**<br>
{{ config('syndicate.name') }} ({{ config('syndicate.acronym') }})<br>
*«{{ config('syndicate.slogan', 'En unidad permanente, TODOS SOMOS LA FUERZA') }}»*
</x-mail::message>
