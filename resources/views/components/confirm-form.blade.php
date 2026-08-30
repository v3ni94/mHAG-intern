@props(['action', 'method' => 'POST', 'confirm' => 'Sind Sie sicher?', 'label' => null, 'icon' => null, 'class' => 'btn btn-sm btn-outline-danger'])
<form method="POST" action="{{ $action }}" class="d-inline"
      onsubmit="return confirm(@js($confirm));">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    <button type="submit" class="{{ $class }}">
        @if ($icon)<i class="bi {{ $icon }}"></i>@endif
        {{ $label ?? $slot }}
    </button>
</form>
