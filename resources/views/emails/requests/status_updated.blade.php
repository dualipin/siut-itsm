<x-mail::message>
# Actualización en su Solicitud

Estimado(a) {{ $userRequest->user->name }},

El estado de su solicitud **{{ $userRequest->folio }}** ha cambiado a: **{{ $userRequest->status->getLabel() }}**.

@if($userRequest->resolution_notes)
**Notas adicionales:**
{{ $userRequest->resolution_notes }}
@endif

<x-mail::button :url="config('app.url') . '/admin/user-requests/' . $userRequest->id">
Ver Detalles
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
