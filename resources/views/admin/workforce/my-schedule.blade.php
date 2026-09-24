@extends('layouts.admin')

@section('title', __('My schedule') . ' | Admin')

@section('content')
<div class="admin-page-shell">
    <x-admin.page-header :kicker="__('Workforce')" :title="__('My schedule')" :description="__('See your current and upcoming published work shifts without mixing them with POS cash shifts.')">
        <a href="{{ route('admin.workforce.time-clock') }}" class="btn btn-primary btn-text-icon"><i class="mdi mdi-clock-check-outline"></i><span>{{ __('My time clock') }}</span></a>
        @if(auth()->user()?->hasPermission('workforce.view'))
            <a href="{{ route('admin.workforce.schedule.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-calendar-multiselect-outline"></i><span>{{ __('Manager schedule') }}</span></a>
        @endif
    </x-admin.page-header>

    @if(!$employee)
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="admin-empty-state py-5">
                    <div class="empty-icon"><i class="mdi mdi-account-alert-outline"></i></div>
                    <h4 class="mb-2">{{ __('Employee profile not configured') }}</h4>
                    <p class="text-muted mb-0">{{ __('Your account must be linked to an employee profile before work shifts can be assigned to you.') }}</p>
                </div>
            </div>
        </div>
    @else
        <div class="row g-3">
            @forelse($shifts as $shift)
                @php
                    $minutes = $shift->durationMinutes();
                    $hours = intdiv($minutes, 60);
                    $remainder = $minutes % 60;
                    $current = $shift->starts_at <= now() && $shift->ends_at >= now();
                @endphp
                <div class="col-lg-6 col-xxl-4">
                    <div class="admin-card h-100">
                        <div class="admin-card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <div class="admin-inline-label">{{ $shift->starts_at->format('l') }}</div>
                                    <h3 class="mb-1">{{ $shift->starts_at->format('d M Y') }}</h3>
                                </div>
                                <span class="badge admin-status-badge {{ $current ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $current ? __('Current shift') : __('Upcoming') }}</span>
                            </div>

                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="admin-stat-icon"><i class="mdi mdi-clock-outline"></i></span>
                                <div>
                                    <div class="fw-bold fs-5">{{ $shift->starts_at->format('H:i') }} → {{ $shift->ends_at->format('H:i') }}</div>
                                    <div class="text-muted small">{{ __(':hours h :minutes m', ['hours' => $hours, 'minutes' => $remainder]) }}</div>
                                </div>
                            </div>

                            @if($shift->location)
                                <div class="mb-2"><i class="mdi mdi-map-marker-outline me-1"></i><span class="fw-semibold">{{ $shift->location }}</span></div>
                            @endif
                            @if($shift->notes)
                                <div class="text-muted small mt-3">{{ $shift->notes }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="admin-card">
                        <div class="admin-card-body">
                            <div class="admin-empty-state py-5">
                                <div class="empty-icon"><i class="mdi mdi-calendar-blank-outline"></i></div>
                                <h4 class="mb-2">{{ __('No upcoming shifts') }}</h4>
                                <p class="text-muted mb-0">{{ __('Published work shifts assigned to you will appear here.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    @endif
</div>
@endsection
