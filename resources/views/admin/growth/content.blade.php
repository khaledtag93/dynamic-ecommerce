@extends('admin.growth.layout')

@section('growth-module-content')
<div class="gm-section-heading">
    <div>
        <h3>{{ __('Journey setup') }}</h3>
        <p>{{ __('Open only the area you need. This keeps a large Growth setup readable even when you have many campaigns and rules.') }}</p>
    </div>
</div>

<details class="gm-panel gm-module" open>
    <summary>
        <div class="gm-module-summary">
            <span class="gm-module-icon"><i class="mdi mdi-bullhorn-outline"></i></span>
            <div><div class="gm-module-title">{{ __('Campaigns') }}</div><div class="gm-mini">{{ __('Customer journeys that connect an audience, trigger, message, and offer.') }}</div></div>
        </div>
        <span class="gm-count">{{ $campaigns->count() }}</span>
    </summary>
    <div class="gm-module-body">
        <div class="gm-section mt-3"><div></div><a href="{{ route('admin.growth.campaigns.create') }}" class="btn btn-sm btn-primary">{{ __('Create campaign') }}</a></div>
        @if($campaigns->isEmpty())
            <div class="gm-empty">{{ __('No campaigns yet. Create the first journey when you are ready.') }}</div>
        @else
            <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
            @foreach($campaigns->take(12) as $campaign)
                <tr>
                    <td><strong>{{ $campaign->name }}</strong><div class="gm-mini">{{ $campaign->campaign_key }}</div></td>
                    <td>{{ strtoupper((string) $campaign->channel) }}</td>
                    <td>{{ $campaign->priority }}</td>
                    <td>{{ $campaign->is_active ? __('Active') : __('Inactive') }}</td>
                    <td><div class="gm-actions"><a href="{{ route('admin.growth.campaigns.edit', $campaign) }}" class="btn btn-sm btn-outline-dark">{{ __('Edit') }}</a><form method="POST" action="{{ route('admin.growth.campaigns.toggle', $campaign) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary">{{ $campaign->is_active ? __('Disable') : __('Enable') }}</button></form></div></td>
                </tr>
            @endforeach
            </tbody></table></div>
        @endif
    </div>
</details>

<details class="gm-panel gm-module">
    <summary>
        <div class="gm-module-summary">
            <span class="gm-module-icon"><i class="mdi mdi-source-branch"></i></span>
            <div><div class="gm-module-title">{{ __('Automation rules') }}</div><div class="gm-mini">{{ __('Control when a journey can run and how often it may repeat.') }}</div></div>
        </div>
        <span class="gm-count">{{ $rules->count() }}</span>
    </summary>
    <div class="gm-module-body">
        <div class="gm-section mt-3"><div></div><a href="{{ route('admin.growth.rules.create') }}" class="btn btn-sm btn-primary">{{ __('Create rule') }}</a></div>
        @if($rules->isEmpty())
            <div class="gm-empty">{{ __('No automation rules yet.') }}</div>
        @else
            <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Rule') }}</th><th>{{ __('Trigger') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
            @foreach($rules->take(12) as $rule)
                <tr>
                    <td><strong>{{ $rule->name }}</strong><div class="gm-mini">{{ $rule->rule_key }}</div></td>
                    <td>{{ $rule->trigger_event ?? '—' }}</td>
                    <td>{{ $rule->priority }}</td>
                    <td>{{ $rule->is_active ? __('Active') : __('Inactive') }}</td>
                    <td><div class="gm-actions"><a href="{{ route('admin.growth.rules.edit', $rule) }}" class="btn btn-sm btn-outline-dark">{{ __('Edit') }}</a><form method="POST" action="{{ route('admin.growth.rules.toggle', $rule) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary">{{ $rule->is_active ? __('Disable') : __('Enable') }}</button></form></div></td>
                </tr>
            @endforeach
            </tbody></table></div>
        @endif
    </div>
</details>

<details class="gm-panel gm-module">
    <summary>
        <div class="gm-module-summary">
            <span class="gm-module-icon"><i class="mdi mdi-message-text-outline"></i></span>
            <div><div class="gm-module-title">{{ __('Templates') }}</div><div class="gm-mini">{{ __('Reusable Arabic and English message content for your campaigns.') }}</div></div>
        </div>
        <span class="gm-count">{{ $templates->count() }}</span>
    </summary>
    <div class="gm-module-body">
        <div class="gm-section mt-3"><div></div><a href="{{ route('admin.growth.templates.create') }}" class="btn btn-sm btn-primary">{{ __('Create template') }}</a></div>
        @if($templates->isEmpty())
            <div class="gm-empty">{{ __('No templates yet.') }}</div>
        @else
            <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Template') }}</th><th>{{ __('Locale') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
            @foreach($templates->take(12) as $template)
                <tr><td><strong>{{ $template->name }}</strong><div class="gm-mini">{{ $template->template_key }}</div></td><td>{{ strtoupper((string) $template->locale) }}</td><td>{{ strtoupper((string) $template->channel) }}</td><td><a href="{{ route('admin.growth.templates.edit', $template) }}" class="btn btn-sm btn-outline-dark">{{ __('Edit') }}</a></td></tr>
            @endforeach
            </tbody></table></div>
        @endif
    </div>
</details>

<details class="gm-panel gm-module">
    <summary>
        <div class="gm-module-summary">
            <span class="gm-module-icon"><i class="mdi mdi-account-group-outline"></i></span>
            <div><div class="gm-module-title">{{ __('Audience segments') }}</div><div class="gm-mini">{{ __('Reusable customer groups for consistent targeting across journeys.') }}</div></div>
        </div>
        <span class="gm-count">{{ $segments->count() }}</span>
    </summary>
    <div class="gm-module-body">
        <div class="gm-section mt-3"><div></div><a href="{{ route('admin.growth.segments.create') }}" class="btn btn-sm btn-primary">{{ __('Create segment') }}</a></div>
        @if($segments->isEmpty())
            <div class="gm-empty">{{ __('No audience segments yet.') }}</div>
        @else
            <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Segment') }}</th><th>{{ __('Type') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
            @foreach($segments->take(12) as $segment)
                <tr><td><strong>{{ $segment->name }}</strong><div class="gm-mini">{{ $segment->segment_key }}</div></td><td>{{ $segment->audience_type ?? '—' }}</td><td>{{ $segment->priority }}</td><td><a href="{{ route('admin.growth.segments.edit', $segment) }}" class="btn btn-sm btn-outline-dark">{{ __('Edit') }}</a></td></tr>
            @endforeach
            </tbody></table></div>
        @endif
    </div>
</details>

<details class="gm-panel gm-module">
    <summary>
        <div class="gm-module-summary">
            <span class="gm-module-icon"><i class="mdi mdi-flask-outline"></i></span>
            <div><div class="gm-module-title">{{ __('Experiments') }}</div><div class="gm-mini">{{ __('Compare controlled variants before deciding which message or offer should stay.') }}</div></div>
        </div>
        <span class="gm-count">{{ $experiments->count() }}</span>
    </summary>
    <div class="gm-module-body">
        <div class="gm-section mt-3"><div></div><a href="{{ route('admin.growth.experiments.create') }}" class="btn btn-sm btn-primary">{{ __('Create experiment') }}</a></div>
        @if($experiments->isEmpty())
            <div class="gm-empty">{{ __('No experiments yet.') }}</div>
        @else
            <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Experiment') }}</th><th>{{ __('Campaign') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
            @foreach($experiments->take(12) as $experiment)
                <tr>
                    <td><strong>{{ $experiment->name }}</strong><div class="gm-mini">{{ $experiment->experiment_key }}</div></td>
                    <td>{{ $experiment->campaign?->name ?? '—' }}</td>
                    <td>{{ $experiment->priority }}</td>
                    <td>{{ $experiment->is_active ? __('Active') : __('Inactive') }}</td>
                    <td><div class="gm-actions"><a href="{{ route('admin.growth.experiments.edit', $experiment) }}" class="btn btn-sm btn-outline-dark">{{ __('Edit') }}</a><form method="POST" action="{{ route('admin.growth.experiments.toggle', $experiment) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary">{{ $experiment->is_active ? __('Disable') : __('Enable') }}</button></form></div></td>
                </tr>
            @endforeach
            </tbody></table></div>
        @endif
    </div>
</details>
@endsection
