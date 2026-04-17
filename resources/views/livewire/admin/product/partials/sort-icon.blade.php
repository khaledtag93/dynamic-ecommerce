@if ($sortField === $field)
    <i class="mdi {{ $sortDirection === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }} ms-1"></i>
@endif
