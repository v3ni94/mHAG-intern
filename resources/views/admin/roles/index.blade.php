@extends('layouts.app')

@section('title', 'Rollen')

@section('content')
    <x-page-header title="Rollen und Berechtigungen" label="Administration">
        <a href="{{ route('admin.roles.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Neue Rolle</a>
    </x-page-header>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Rolle</th>
                        <th>Typ</th>
                        <th class="num">Berechtigungen</th>
                        <th class="num">Benutzer</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td class="fw-semibold">{{ $role->name }}</td>
                            <td>
                                @if (in_array($role->name, $systemRoles, true))
                                    <x-status-badge severity="neutral" label="Standardrolle" />
                                @else
                                    <x-status-badge severity="info" label="Eigene Rolle" />
                                @endif
                            </td>
                            <td class="num">{{ $role->permissions_count }}</td>
                            <td class="num">{{ $role->users_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil"></i> Berechtigungen
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3">
        Die Namen der Standardrollen sind nicht änderbar und Standardrollen können nicht gelöscht werden.
        Berechtigungen sind für alle Rollen administrierbar.
    </p>
@endsection
