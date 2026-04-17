@extends('admin.growth.layout')

@section('growth-module-content')
<div class="gm-three">
    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Campaigns') }}</h4><div class="gm-mini">{{ __('Live and draft journeys.') }}</div></div><a href="{{ route('admin.growth.campaigns.create') }}" class="btn btn-sm btn-primary">{{ __('Create') }}</a></div>
        @if($campaigns->isEmpty())<div class="gm-empty">{{ __('No campaigns yet.') }}</div>@else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead><tbody>
        @foreach($campaigns->take(12) as $campaign)
        <tr><td><strong>{{ $campaign->name }}</strong><div class="gm-mini">{{ $campaign->campaign_key }}</div></td><td>{{ strtoupper((string) $campaign->channel) }}</td><td>{{ $campaign->priority }}</td><td>{{ $campaign->is_active ? __('Active') : __('Inactive') }}</td><td><div class="gm-actions"><a href="{{ route('admin.growth.campaigns.edit', $campaign) }}" class="btn btn-sm btn-outline-dark">{{ __('Edit') }}</a><form method="POST" action="{{ route('admin.growth.campaigns.toggle', $campaign) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary">{{ $campaign->is_active ? __('Disable') : __('Enable') }}</button></form></div></td></tr>
        @endforeach
        </tbody></table></div>@endif
    </div>

    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Automation rules') }}</h4><div class="gm-mini">{{ __('Eligibility, delays, and cooldown logic.') }}</div></div><a href="{{ route('admin.growth.rules.create') }}" class="btn btn-sm btn-outline-primary">{{ __('Create') }}</a></div>
        @if($rules->isEmpty())<div class="gm-empty">{{ __('No automation rules yet.') }}</div>@else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Rule') }}</th><th>{{ __('Trigger') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead><tbody>
        @foreach($rules->take(12) as $rule)
        <tr><td><strong>{{ $rule->name }}</strong><div class="gm-mini">{{ $rule->rule_key }}</div></td><td>{{ $rule->trigger_event ?? '—' }}</td><td>{{ $rule->priority }}</td><td>{{ $rule->is_active ? __('Active') : __('Inactive') }}</td><td><div class="gm-actions"><a href="{{ route('admin.growth.rules.edit', $rule) }}" class="btn btn-sm btn-outline-dark">{{ __('Edit') }}</a><form method="POST" action="{{ route('admin.growth.rules.toggle', $rule) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary">{{ $rule->is_active ? __('Disable') : __('Enable') }}</button></form></div></td></tr>
        @endforeach
        </tbody></table></div>@endif
    </div>

    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Templates') }}</h4><div class="gm-mini">{{ __('Localized message content.') }}</div></div><a href="{{ route('admin.growth.templates.create') }}" class="btn btn-sm btn-outline-dark">{{ __('Create') }}</a></div>
        @if($templates->isEmpty())<div class="gm-empty">{{ __('No templates yet.') }}</div>@else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Template') }}</th><th>{{ __('Locale') }}</th><th>{{ __('Channel') }}</th><th></th></tr></thead><tbody>
        @foreach($templates->take(12) as $template)
        <tr><td><strong>{{ $template->name }}</strong><div class="gm-mini">{{ $template->template_key }}</div></td><td>{{ strtoupper((string) $template->locale) }}</td><td>{{ strtoupper((string) $template->channel) }}</td><td><a href="{{ route('admin.growth.templates.edit', $template) }}" class="btn btn-sm btn-outline-dark">{{ __('Edit') }}</a></td></tr>
        @endforeach
        </tbody></table></div>@endif
    </div>
</div>

<div class="gm-two">
    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Audience segments') }}</h4><div class="gm-mini">{{ __('Reusable targeting blocks.') }}</div></div><a href="{{ route('admin.growth.segments.create') }}" class="btn btn-sm btn-outline-secondary">{{ __('Create') }}</a></div>
        @if($segments->isEmpty())<div class="gm-empty">{{ __('No audience segments yet.') }}</div>@else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Segment') }}</th><th>{{ __('Type') }}</th><th>{{ __('Priority') }}</th><th></th></tr></thead><tbody>
        @foreach($segments->take(12) as $segment)
        <tr><td><strong>{{ $segment->name }}</strong><div class="gm-mini">{{ $segment->segment_key }}</div></td><td>{{ $segment->audience_type ?? '—' }}</td><td>{{ $segment->priority }}</td><td><a href="{{ route('admin.growth.segments.edit', $segment) }}" class="btn btn-sm btn-outline-dark">{{ __('Edit') }}</a></td></tr>
        @endforeach
        </tbody></table></div>@endif
    </div>

    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Experiments') }}</h4><div class="gm-mini">{{ __('A/B offers and messaging experiments.') }}</div></div><a href="{{ route('admin.growth.experiments.create') }}" class="btn btn-sm btn-outline-success">{{ __('Create') }}</a></div>
        @if($experiments->isEmpty())<div class="gm-empty">{{ __('No experiments yet.') }}</div>@else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Experiment') }}</th><th>{{ __('Campaign') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead><tbody>
        @foreach($experiments->take(12) as $experiment)
        <tr><td><strong>{{ $experiment->name }}</strong><div class="gm-mini">{{ $experiment->experiment_key }}</div></td><td>{{ $experiment->campaign?->name ?? '—' }}</td><td>{{ $experiment->priority }}</td><td>{{ $experiment->is_active ? __('Active') : __('Inactive') }}</td><td><div class="gm-actions"><a href="{{ route('admin.growth.experiments.edit', $experiment) }}" class="btn btn-sm btn-outline-dark">{{ __('Edit') }}</a><form method="POST" action="{{ route('admin.growth.experiments.toggle', $experiment) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary">{{ $experiment->is_active ? __('Disable') : __('Enable') }}</button></form></div></td></tr>
        @endforeach
        </tbody></table></div>@endif
    </div>
</div>
@endsection
