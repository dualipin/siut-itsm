<x-mail::message>
# Nueva Solicitud Recibida

Se ha registrado una nueva solicitud con el folio **{{ $userRequest->folio }}**.

**Agremiado:** {{ $userRequest->user->name }} {{ $userRequest->user->surnames }}
**Tipo:** {{ $userRequest->requestType->name }}
**Motivo:** {{ $userRequest->reason }}

<x-mail::button :url="config('app.url') . '/admin/user-requests/' . $userRequest->id">
Ver Solicitud
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
