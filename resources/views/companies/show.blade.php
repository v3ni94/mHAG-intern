@extends('layouts.app')

@section('title', $entity->display_name)

@section('content')
    <x-page-header :title="$entity->display_name" label="Unternehmensakte">
        @include('persons.partials.entity-status', ['entity' => $entity])
        @can('companies.update')
            <a href="{{ route('companies.edit', $entity) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil"></i> Bearbeiten
            </a>
        @endcan
        @can('companies.archive')
            @if ($entity->status === 'archived')
                <x-confirm-form :action="route('companies.archive', $entity)" method="POST"
                                confirm="Diese Unternehmensakte wieder aktivieren?"
                                label="Reaktivieren" icon="bi-arrow-counterclockwise"
                                class="btn btn-sm btn-outline-success" />
            @else
                <x-confirm-form :action="route('companies.archive', $entity)" method="POST"
                                confirm="Diese Unternehmensakte archivieren? Die Daten bleiben erhalten."
                                label="Archivieren" icon="bi-archive"
                                class="btn btn-sm btn-outline-danger" />
            @endif
        @endcan
    </x-page-header>

    <div class="mb-2 text-muted small">
        <span class="me-3"><i class="bi bi-hash"></i> {{ $entity->internal_number }}</span>
        @if ($entity->company?->legal_form)
            <span class="me-3">{{ $entity->company->legal_form }}</span>
        @endif
        @if (is_array($entity->tags) && count($entity->tags))
            @foreach ($entity->tags as $tag)
                <span class="badge text-bg-light border me-1">{{ $tag }}</span>
            @endforeach
        @endif
    </div>

    <ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" style="white-space: nowrap;">
        @foreach ($tabs as $key => $meta)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                   href="{{ route('companies.show', [$entity, 'tab' => $key]) }}">
                    <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
                </a>
            </li>
        @endforeach
    </ul>

    @include('companies.tabs.'.$tab)
@endsection
