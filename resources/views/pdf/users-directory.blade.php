@extends('document.layout')

@section('title', 'Padrón de Usuarios')

@section('content')
<style>
    .directory-meta {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
        margin-bottom: 12px;
        border-bottom: 2px solid {{ $documentColors['primary'] }};
    }

    .directory-meta td {
        padding-bottom: 8px;
        vertical-align: bottom;
    }

    .directory-title {
        font-size: 16px;
        font-weight: bold;
        color: {{ $documentColors['primary'] }};
        margin: 0;
    }

    .directory-stats {
        text-align: right;
        font-size: 10px;
        color: #374151;
        line-height: 1.45;
    }

    .directory-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
        table-layout: fixed;
    }

    .directory-table thead {
        display: table-header-group;
    }

    .directory-table tr {
        page-break-inside: avoid;
    }

    .directory-table th {
        background-color: {{ $documentColors['primary'] }};
        color: {{ $documentColors['primary_content'] }};
        padding: 7px 6px;
        text-align: left;
        font-weight: bold;
        font-size: 9px;
        letter-spacing: 0.02em;
        word-wrap: break-word;
    }

    .directory-table th.col-index {
        width: 4.5%;
        text-align: right;
        padding-right: 8px;
    }

    .directory-table td {
        padding: 6px 6px;
        vertical-align: top;
        line-height: 1.35;
        border-bottom: 1px solid {{ $documentColors['base_300'] }};
        word-wrap: break-word;
        overflow-wrap: break-word;
        color: {{ $documentColors['base_content'] }};
    }

    .directory-table tbody tr:nth-child(even) td {
        background-color: {{ $documentColors['base_100'] }};
    }

    .directory-table td.col-index {
        text-align: right;
        padding-right: 8px;
        color: #6b7280;
    }

    .directory-empty {
        padding: 20px 12px;
        text-align: center;
        color: #4b5563;
        font-size: 10px;
    }
</style>

<table class="directory-meta">
    <tr>
        <td>
            <h2 class="directory-title">Padr&oacute;n de Usuarios</h2>
        </td>
        <td class="directory-stats">
            <div><strong>Registros:</strong> {{ $rows->count() }}</div>
            <div><strong>Emitido:</strong> {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table class="directory-table">
    <thead>
        <tr>
            <th class="col-index">#</th>
            @foreach ($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $index => $row)
            <tr>
                <td class="col-index">{{ $index + 1 }}</td>
                @foreach ($columnKeys as $key)
                    <td>{{ $row[$key] }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($headers) + 1 }}" class="directory-empty">
                    No hay usuarios que coincidan con los filtros seleccionados.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
@endsection
