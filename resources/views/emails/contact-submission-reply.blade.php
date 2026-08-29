<x-mail::message>
# Estimado(a) {{ $submission->name }},

Hemos recibido tu mensaje a través de nuestro portal y a continuación te compartimos nuestra respuesta oficial:

<x-mail::panel>
{{ $reply->message }}
</x-mail::panel>

Si tienes alguna duda adicional o requieres mayor información, puedes responder directamente a este correo o ponerte en contacto con nosotros a través de nuestros canales oficiales.

<x-mail::panel>
**Tu mensaje original:**

{{ $submission->message }}
</x-mail::panel>

Atentamente,<br>
**{{ config('syndicate.name') }} ({{ config('syndicate.acronym') }})**<br>
{{ config('syndicate.address') }} &bull; Tel: {{ config('syndicate.phone') }}
</x-mail::message>
