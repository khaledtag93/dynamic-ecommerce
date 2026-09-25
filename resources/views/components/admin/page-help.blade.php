@props([
    'title' => __('Page help'),
    'intro' => null,
    'items' => [],
    'buttonLabel' => __('Help'),
])

@php
    $modalId = $attributes->get('id') ?: 'admin-page-help-'.substr(md5((string) $title), 0, 10);
@endphp

@once
<style>
.admin-page-help-trigger{display:inline-flex;align-items:center;gap:.45rem;border-radius:999px;padding:.52rem .78rem;font-weight:800;border:1px solid var(--admin-border);background:var(--admin-surface);color:var(--admin-text);box-shadow:0 8px 22px rgba(15,23,42,.06)}
.admin-page-help-trigger:hover{background:var(--admin-surface-alt);color:var(--admin-text);text-decoration:none}.admin-page-help-modal .modal-content{border:1px solid var(--admin-border);border-radius:22px;background:var(--admin-surface);color:var(--admin-text);box-shadow:0 28px 70px rgba(15,23,42,.18)}
.admin-page-help-modal .modal-header{align-items:flex-start;border-bottom:1px solid var(--admin-border);padding:1.2rem 1.3rem}.admin-page-help-modal .modal-body{padding:1.25rem 1.3rem}.admin-page-help-intro{margin:.35rem 0 0;color:var(--admin-muted);line-height:1.7;max-width:72ch}
.admin-page-help-list{display:grid;gap:.8rem}.admin-page-help-item{padding:1rem;border:1px solid var(--admin-border);border-radius:16px;background:var(--admin-surface-alt)}.admin-page-help-item h6{margin:0 0 .35rem;font-weight:800;color:var(--admin-text)}
.admin-page-help-item p{margin:0;color:var(--admin-muted);line-height:1.65}.admin-page-help-example{margin-top:.7rem;padding:.7rem .8rem;border-radius:12px;background:var(--admin-surface);border:1px dashed var(--admin-border);font-size:.88rem;color:var(--admin-text)}
body[dir='rtl'] .admin-page-help-modal{text-align:right}
@media (max-width:575.98px){.admin-page-help-trigger span{display:none}.admin-page-help-trigger{width:42px;height:42px;padding:0;justify-content:center}.admin-page-help-modal .modal-dialog{margin:.65rem}}
</style>
@endonce

<button type="button"
        class="admin-page-help-trigger"
        data-bs-toggle="modal"
        data-bs-target="#{{ $modalId }}"
        aria-controls="{{ $modalId }}"
        aria-label="{{ $buttonLabel }}">
    <i class="mdi mdi-help-circle-outline" aria-hidden="true"></i>
    <span>{{ $buttonLabel }}</span>
</button>

<div class="modal fade admin-page-help-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="admin-chip mb-2"><i class="mdi mdi-lightbulb-on-outline"></i>{{ __('Simple guide') }}</div>
                    <h5 class="modal-title">{{ $title }}</h5>
                    @if($intro)
                        <p class="admin-page-help-intro">{{ $intro }}</p>
                    @endif
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close help') }}"></button>
            </div>
            <div class="modal-body">
                <div class="admin-page-help-list">
                    @foreach($items as $item)
                        <section class="admin-page-help-item">
                            <h6>{{ $item['title'] ?? '' }}</h6>
                            @if(!empty($item['body']))
                                <p>{{ $item['body'] }}</p>
                            @endif
                            @if(!empty($item['example']))
                                <div class="admin-page-help-example"><strong>{{ __('Example') }}:</strong> {{ $item['example'] }}</div>
                            @endif
                        </section>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
