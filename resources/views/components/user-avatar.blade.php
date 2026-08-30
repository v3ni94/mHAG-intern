@props(['user' => null, 'size' => 32, 'showInitials' => false])
{{--
    Rundes Profilbild (Anforderung vom 30.08.2026).
    Ohne hinterlegtes Bild erscheint das Firmenzeichen der Müller Holding AG;
    auf Wunsch stattdessen die Initialen.
--}}
@php
    $px = (int) $size;
    $hasAvatar = $user && method_exists($user, 'hasAvatar') && $user->hasAvatar();
@endphp
@if ($hasAvatar)
    <img src="{{ route('profile.avatar.show', $user->id) }}"
         alt="Profilbild {{ $user->name }}"
         width="{{ $px }}" height="{{ $px }}"
         {{ $attributes->merge(['class' => 'avatar-circle']) }}
         style="width: {{ $px }}px; height: {{ $px }}px;">
@elseif ($showInitials && $user)
    <span {{ $attributes->merge(['class' => 'avatar-circle avatar-initials']) }}
          style="width: {{ $px }}px; height: {{ $px }}px; font-size: {{ max(10, (int) round($px * 0.4)) }}px;"
          aria-label="Profil {{ $user->name }}">{{ $user->initials() }}</span>
@else
    <img src="{{ asset('images/logo-mhag-transparent.png') }}"
         alt="{{ $user ? 'Kein Profilbild hinterlegt' : 'Müller Holding AG' }}"
         width="{{ $px }}" height="{{ $px }}"
         {{ $attributes->merge(['class' => 'avatar-circle avatar-fallback']) }}
         style="width: {{ $px }}px; height: {{ $px }}px;">
@endif
