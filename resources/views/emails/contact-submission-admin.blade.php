<x-mail::message>
# Nuevo Mensaje de Contacto

Se ha recibido una nueva solicitud a través del formulario del portal web:

<x-mail::table>
| Campo | Detalle |
| :--- | :--- |
| **Remitente** | {{ $submission->name }} |
| **Correo** | [{{ $submission->email }}](mailto:{{ $submission->email }}) |
@if ($submission->phone)
| **Teléfono** | [{{ $submission->phone }}](tel:{{ $submission->phone }}) |
@endif
@if ($submission->subject)
| **Asunto** | {{ $submission->subject }} |
@endif
</x-mail::table>

<x-mail::panel>
**Mensaje recibido:**

{{ $submission->message }}
</x-mail::panel>

<x-mail::button :url="url('/portal/contact-submissions/' . $submission->id)">
Ver en el Portal
</x-mail::button>

Saludos cordiales,<br>
{{ config('app.name') }}
</x-mail::message>