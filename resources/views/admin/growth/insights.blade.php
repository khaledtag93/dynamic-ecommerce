@extends('admin.growth.layout')

@section('growth-module-content')
<div class="gm-grid">
    <div class="gm-card"><div class="gm-mini">{{ __('Attributed revenue') }}</div><div class="gm-value">{{ number_format((float) ($attributionSummary['attributed_revenue'] ?? 0), 2) }}</div><div class="gm-help">{{ __('Revenue matched to growth touches.') }}</div></div>
    <div class="gm-card"><div class="gm-mini">{{ __('30d revenue lift') }}</div><div class="gm-value">{{ number_format((float) ($attributionSummary['lift_revenue_30d'] ?? 0), 2) }}</div><div class="gm-help">{{ __('Recent attributed lift from growth actions.') }}</div></div>
    <div class="gm-card"><div class="gm-mini">{{ __('Average churn risk') }}</div><div class="gm-value">{{ number_format((float) ($predictiveSummary['avg_churn_risk'] ?? 0), 2) }}</div><div class="gm-help">{{ __('Average predicted churn score.') }}</div></div>
    <div class="gm-card"><div class="gm-mini">{{ __('Average 90d retention') }}</div><div class="gm-value">{{ number_format((float) ($cohortSummary['average_retention_90d'] ?? 0), 2) }}%</div><div class="gm-help">{{ __('Average repeat retention across recent cohorts.') }}</div></div>
</div>

<div class="gm-two">
    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Attribution breakdown') }}</h4><div class="gm-mini">{{ __('Top campaigns by attributed revenue and conversions.') }}</div></div></div>
        @if($attributionBreakdown->isEmpty())<div class="gm-empty">{{ __('No attribution rows yet.') }}</div>@else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Revenue') }}</th><th>{{ __('Orders') }}</th><th>{{ __('Touches') }}</th></tr></thead><tbody>
        @foreach($attributionBreakdown->take(12) as $row)
        <tr><td>{{ $row['campaign_name'] ?? $row['campaign'] ?? '—' }}</td><td>{{ number_format((float) ($row['revenue'] ?? $row['attributed_revenue'] ?? 0), 2) }}</td><td>{{ $row['orders'] ?? $row['order_count'] ?? 0 }}</td><td>{{ $row['touches'] ?? $row['touch_count'] ?? 0 }}</td></tr>
        @endforeach
        </tbody></table></div>@endif
    </div>

    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Experiment performance') }}</h4><div class="gm-mini">{{ __('How active variants are performing.') }}</div></div></div>
        @if($experimentPerformance->isEmpty())<div class="gm-empty">{{ __('No experiment performance yet.') }}</div>@else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Experiment') }}</th><th>{{ __('Messages') }}</th><th>{{ __('Conversions') }}</th><th>{{ __('Revenue') }}</th></tr></thead><tbody>
        @foreach($experimentPerformance->take(12) as $row)
        <tr><td>{{ $row['name'] ?? $row['experiment_name'] ?? '—' }}</td><td>{{ $row['messages'] ?? $row['message_count'] ?? 0 }}</td><td>{{ $row['conversions'] ?? $row['conversion_count'] ?? 0 }}</td><td>{{ number_format((float) ($row['revenue'] ?? 0), 2) }}</td></tr>
        @endforeach
        </tbody></table></div>@endif
    </div>
</div>

<div class="gm-two">
    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Cohort retention') }}</h4><div class="gm-mini">{{ __('Latest retention rows.') }}</div></div></div>
        @if($cohortRows->isEmpty())<div class="gm-empty">{{ __('No cohort snapshots yet.') }}</div>@else
        <div class="table-responsive"><table class="gm-table"><thead><tr><th>{{ __('Cohort') }}</th><th>{{ __('Customers') }}</th><th>{{ __('30d') }}</th><th>{{ __('60d') }}</th><th>{{ __('90d') }}</th></tr></thead><tbody>
        @foreach($cohortRows->take(12) as $row)
        <tr><td>{{ $row['label'] ?? $row['cohort_label'] ?? '—' }}</td><td>{{ $row['customers'] ?? $row['customer_count'] ?? 0 }}</td><td>{{ number_format((float) ($row['retention_30d'] ?? 0), 2) }}%</td><td>{{ number_format((float) ($row['retention_60d'] ?? 0), 2) }}%</td><td>{{ number_format((float) ($row['retention_90d'] ?? 0), 2) }}%</td></tr>
        @endforeach
        </tbody></table></div>@endif
    </div>

    <div class="gm-panel">
        <div class="gm-section"><div><h4>{{ __('Predictive and adaptive signals') }}</h4><div class="gm-mini">{{ __('Top scores and learning rows.') }}</div></div></div>
        <div class="gm-stack">
            <div class="gm-box"><strong>{{ __('High churn-risk customers') }}</strong>
                @if($predictiveRows->isEmpty())<div class="gm-help mt-2">{{ __('No predictive rows yet.') }}</div>@else
                    <div class="gm-stack mt-2">@foreach($predictiveRows->take(5) as $row)<div class="d-flex justify-content-between gap-3"><span>{{ $row['name'] ?? $row['customer_name'] ?? __('Customer') }}</span><strong>{{ number_format((float) ($row['churn_risk'] ?? $row['score'] ?? 0), 2) }}</strong></div>@endforeach</div>
                @endif
            </div>
            <div class="gm-box"><strong>{{ __('Adaptive learning rows') }}</strong>
                @if($adaptiveLearningRows->isEmpty())<div class="gm-help mt-2">{{ __('No adaptive learning rows yet.') }}</div>@else
                    <div class="gm-stack mt-2">@foreach($adaptiveLearningRows->take(5) as $row)<div class="d-flex justify-content-between gap-3"><span>{{ $row['name'] ?? $row['label'] ?? __('Row') }}</span><strong>{{ number_format((float) ($row['score'] ?? $row['value'] ?? 0), 2) }}</strong></div>@endforeach</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
