@php
    $templateCount = 0;
    $activeTemplateCount = 0;
    $channelCount = count($channels ?? []);
    $localeCount = count($locales ?? []);

    foreach (($templateMatrix ?? []) as $eventTemplates) {
        foreach ($eventTemplates as $channelTemplates) {
            foreach ($channelTemplates as $localeTemplates) {
                foreach ((array) $localeTemplates as $templateItem) {
                    $templateCount++;
                    if (($templateItem->is_active ?? false)) {
                        $activeTemplateCount++;
                    }
                }
            }
        }
    }
@endphp

<div id="notification-templates" class="notification-section-title mt-2">
    <div>
        <div class="notification-section-kicker">{{ __('Content quality') }}</div>
        <h3 class="admin-section-title mb-1">{{ __('Templates & Test Sends') }}</h3>
        <p class="admin-section-subtitle mb-0">{{ __('Preview localized templates, run safe test sends, and verify final customer-facing wording before anything reaches production.') }}</p>
    </div>
    <span class="admin-chip">{{ __('Content workspace') }}</span>
</div>

<div class="notification-workspace-strip">
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Saved templates') }}</div>
        <div class="value">{{ number_format($templateCount) }}</div>
        <div class="caption">{{ __('All event, channel, and locale combinations currently stored.') }}</div>
    </div>
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Active templates') }}</div>
        <div class="value">{{ number_format($activeTemplateCount) }}</div>
        <div class="caption">{{ __('Templates currently enabled for live rendering and sending.') }}</div>
    </div>
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Supported channels') }}</div>
        <div class="value">{{ number_format($channelCount) }}</div>
        <div class="caption">{{ __('Database, email, and WhatsApp stay editable from one workspace.') }}</div>
    </div>
    <div class="notification-metric-card">
        <div class="eyebrow">{{ __('Supported locales') }}</div>
        <div class="value">{{ number_format($localeCount) }}</div>
        <div class="caption">{{ __('Use the same message flow while keeping localized customer copy consistent.') }}</div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="notification-subsection-head">
                    <div>
                        <h4 class="mb-1">{{ __('Template management workspace') }}</h4>
                        <div class="admin-helper-text">{{ __('Edit reusable message copy by event first, then fine-tune it per channel and locale without touching the delivery logic.') }}</div>
                    </div>
                    <div class="notification-pill-row">
                        <span class="admin-chip">{{ __('Content') }}</span>
                        <span class="admin-chip">{{ __('Localization') }}</span>
                    </div>
                </div>

                <div class="notification-note-box mb-4">
                    <div class="fw-semibold mb-1">{{ __('Recommended editing flow') }}</div>
                    <div class="admin-helper-text">{{ __('Choose the event, update the channel-specific message, then render a preview and run one controlled test send before broader production traffic.') }}</div>
                </div>

                <div class="accordion" id="notificationTemplatesAccordion">
                    @foreach($events as $eventKey => $event)
                        <div class="accordion-item border rounded-4 mb-3 overflow-hidden">
                            <h2 class="accordion-header" id="templateHeading{{ $loop->index }}">
                                <button class="accordion-button @if(! $loop->first) collapsed @endif" type="button" data-bs-toggle="collapse" data-bs-target="#templateCollapse{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                                    <div>
                                        <div class="fw-semibold">{{ $event['label'] }}</div>
                                        <div class="admin-helper-text">{{ $event['description'] }}</div>
                                    </div>
                                </button>
                            </h2>
                            <div id="templateCollapse{{ $loop->index }}" class="accordion-collapse collapse @if($loop->first) show @endif" data-bs-parent="#notificationTemplatesAccordion">
                                <div class="accordion-body">
                                    <div class="row g-4">
                                        @foreach(['database', 'email', 'whatsapp'] as $templateChannel)
                                            <div class="col-12">
                                                <div class="notification-subsection-card">
                                                    <div class="notification-subsection-head mb-3">
                                                        <div>
                                                            <h5 class="mb-1">{{ $channels[$templateChannel]['label'] ?? ucfirst($templateChannel) }}</h5>
                                                            <div class="admin-helper-text">{{ $channels[$templateChannel]['description'] ?? '' }}</div>
                                                        </div>
                                                        <span class="badge rounded-pill text-bg-light border">{{ strtoupper($templateChannel) }}</span>
                                                    </div>

                                                    <div class="row g-3">
                                                        @foreach($locales as $localeKey => $localeLabel)
                                                            @php($template = $templateMatrix[$eventKey][$templateChannel][$localeKey][0] ?? null)
                                                            <div class="col-xl-6">
                                                                <form method="POST" action="{{ route('admin.settings.notifications.templates.save') }}" class="notification-form-shell h-100" data-submit-loading>
                                                                    @csrf
                                                                    <input type="hidden" name="event" value="{{ $eventKey }}">
                                                                    <input type="hidden" name="channel" value="{{ $templateChannel }}">
                                                                    <input type="hidden" name="locale" value="{{ $localeKey }}">

                                                                    <div class="d-flex justify-content-between align-items-center mb-3 gap-3 flex-wrap">
                                                                        <div>
                                                                            <div class="fw-semibold">{{ $localeLabel }}</div>
                                                                            <div class="admin-helper-text">{{ __('Event: :event', ['event' => $event['label']]) }}</div>
                                                                        </div>
                                                                        <div class="form-check form-switch notification-switch m-0">
                                                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="tmpl_active_{{ $eventKey }}_{{ $templateChannel }}_{{ $localeKey }}" @checked(old('is_active', $template?->is_active ?? true))>
                                                                            <label class="form-check-label small" for="tmpl_active_{{ $eventKey }}_{{ $templateChannel }}_{{ $localeKey }}">{{ __('Active') }}</label>
                                                                        </div>
                                                                    </div>

                                                                    <div class="row g-3">
                                                                        <div class="col-md-8">
                                                                            <label class="form-label">{{ __('Template name') }}</label>
                                                                            <input type="text" class="form-control" name="name" value="{{ old('name', $template?->name) }}">
                                                                        </div>
                                                                        <div class="col-md-4">
                                                                            <label class="form-label">{{ __('Sort') }}</label>
                                                                            <input type="number" class="form-control" name="sort_order" value="{{ old('sort_order', $template?->sort_order ?? 100) }}">
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <label class="form-label">{{ __('Title') }}</label>
                                                                            <input type="text" class="form-control" name="title" value="{{ old('title', $template?->title) }}" placeholder="{{ __('Internal / in-app title') }}">
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <label class="form-label">{{ __('Email subject') }}</label>
                                                                            <input type="text" class="form-control" name="subject" value="{{ old('subject', $template?->subject) }}" placeholder="{{ __('Used mainly for email') }}">
                                                                        </div>
                                                                        <div class="col-12">
                                                                            <label class="form-label">{{ __('Body') }}</label>
                                                                            <textarea class="form-control" name="body" rows="4" required>{{ old('body', $template?->body) }}</textarea>
                                                                        </div>
                                                                        <div class="col-12">
                                                                            <label class="form-label">{{ __('Tokens') }}</label>
                                                                            <input type="text" class="form-control" name="tokens_text" value="{{ old('tokens_text', implode(', ', $template?->tokens ?? [':store_name', ':customer_name', ':order_number', ':order_status', ':delivery_status'])) }}">
                                                                            <div class="admin-helper-text mt-2">{{ __('Common tokens: :store_name, :customer_name, :order_number, :order_status, :delivery_status, :payment_status, :grand_total, :currency, :tracking_number, :refund_total') }}</div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="d-flex justify-content-end mt-3">
                                                                        <button type="submit" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" data-loading-text="{{ __('Saving template changes...') }}"><i class="mdi mdi-content-save-outline"></i><span>{{ __('Save template changes') }}</span></button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<details class="notification-collapsible mt-4" open>
    <summary>
        <span>{{ __('Preview and test send workspace') }}</span>
        <span class="meta">{{ __('Render real output before any live delivery action') }}</span>
    </summary>
    <div class="notification-collapsible-body">
        <div class="row g-4">
            <div class="col-xl-7">
                <div class="admin-card h-100">
                    <div class="admin-card-body">
                        <div class="notification-subsection-head">
                            <div>
                                <h4 class="mb-1">{{ __('Message preview') }}</h4>
                                <div class="admin-helper-text">{{ __('Render the selected message with a real order so title, subject, and token replacements are all visible before sending.') }}</div>
                            </div>
                            <span class="admin-chip">{{ __('Preview') }}</span>
                        </div>

                        <form method="GET" action="{{ route('admin.settings.notifications.templates') }}" class="row g-3 mb-4" data-submit-loading>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Order') }}</label>
                                <select class="form-select" name="preview_order_id">
                                    @foreach($recentOrders as $orderOption)
                                        <option value="{{ $orderOption->id }}" @selected(($previewState['order_id'] ?? null) == $orderOption->id)>
                                            {{ $orderOption->order_number }} — {{ $orderOption->customer_name ?: __('Guest') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Event') }}</label>
                                <select class="form-select" name="preview_event">
                                    @foreach($events as $eventKey => $event)
                                        <option value="{{ $eventKey }}" @selected(($previewState['event'] ?? '') === $eventKey)>{{ $event['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Channel') }}</label>
                                <select class="form-select" name="preview_channel">
                                    @foreach(['database', 'email', 'whatsapp'] as $previewChannel)
                                        <option value="{{ $previewChannel }}" @selected(($previewState['channel'] ?? '') === $previewChannel)>{{ $channels[$previewChannel]['label'] ?? ucfirst($previewChannel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Locale') }}</label>
                                <select class="form-select" name="preview_locale">
                                    @foreach($locales as $localeKey => $localeLabel)
                                        <option value="{{ $localeKey }}" @selected(($previewState['locale'] ?? '') === $localeKey)>{{ $localeLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="admin-form-actions">
                                    <div class="admin-form-actions-copy">
                                        <div class="admin-form-actions-title">{{ __('Preview before sending') }}</div>
                                        <div class="admin-form-actions-subtitle">{{ __('Render the selected message with real order data so the final copy and tokens look correct before a live send.') }}</div>
                                    </div>
                                    <div class="admin-form-actions-buttons">
                                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" data-loading-text="{{ __('Rendering preview...') }}"><i class="mdi mdi-eye-outline"></i><span>{{ __('Render message preview') }}</span></button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        @if($preview && $previewOrder)
                            <div class="notification-form-shell">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                    <div>
                                        <div class="fw-semibold">{{ __('Order') }}: {{ $previewOrder->order_number }}</div>
                                        <div class="admin-helper-text">{{ $previewOrder->customer_name ?: __('Guest') }} • {{ $previewOrder->customer_email ?: $previewOrder->customer_phone ?: '—' }}</div>
                                    </div>
                                    <span class="admin-chip">{{ $channels[$previewState['channel']]['label'] ?? ucfirst($previewState['channel']) }} / {{ strtoupper($previewState['locale']) }}</span>
                                </div>

                                <div class="notification-grid-2 mb-3">
                                    <div class="notification-subsection-card">
                                        <div class="small text-muted mb-2">{{ __('Title') }}</div>
                                        <div class="fw-semibold">{{ $preview['title'] ?: '—' }}</div>
                                    </div>
                                    <div class="notification-subsection-card">
                                        <div class="small text-muted mb-2">{{ __('Subject') }}</div>
                                        <div class="fw-semibold">{{ $preview['subject'] ?: '—' }}</div>
                                    </div>
                                </div>

                                <div class="notification-subsection-card">
                                    <div class="small text-muted mb-2">{{ __('Rendered body') }}</div>
                                    <div style="white-space: pre-wrap; line-height: 1.8;">{{ $preview['body'] }}</div>
                                </div>

                                <div class="mt-4">
                                    <div class="small text-muted mb-2">{{ __('Resolved tokens') }}</div>
                                    <div class="notification-pill-row">
                                        @foreach(($preview['tokens'] ?? []) as $tokenKey => $tokenValue)
                                            <span class="badge rounded-pill text-bg-light border">{{ $tokenKey }} = {{ $tokenValue }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="admin-helper-text">{{ __('No order is available yet for preview. Create at least one order, then come back to render the message preview.') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="admin-card h-100">
                    <div class="admin-card-body">
                        <div class="notification-subsection-head">
                            <div>
                                <h4 class="mb-1">{{ __('Test send center') }}</h4>
                                <div class="admin-helper-text">{{ __('Trigger a controlled admin-side test send using a real order context before enabling broader production traffic.') }}</div>
                            </div>
                            <span class="admin-chip">{{ __('Controlled test') }}</span>
                        </div>

                        <form method="POST" action="{{ route('admin.settings.notifications.test-send') }}" class="row g-3" data-submit-loading>
                            @csrf
                            <div class="col-12">
                                <label class="form-label">{{ __('Order') }}</label>
                                <select class="form-select" name="order_id" required>
                                    @foreach($recentOrders as $orderOption)
                                        <option value="{{ $orderOption->id }}" @selected(($previewState['order_id'] ?? null) == $orderOption->id)>
                                            {{ $orderOption->order_number }} — {{ $orderOption->customer_name ?: __('Guest') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Event') }}</label>
                                <select class="form-select" name="event" required>
                                    @foreach($events as $eventKey => $event)
                                        <option value="{{ $eventKey }}" @selected(($previewState['event'] ?? '') === $eventKey)>{{ $event['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Channel') }}</label>
                                <select class="form-select" name="channel" required>
                                    @foreach(['database', 'email', 'whatsapp'] as $testChannel)
                                        <option value="{{ $testChannel }}" @selected(($previewState['channel'] ?? '') === $testChannel)>{{ $channels[$testChannel]['label'] ?? ucfirst($testChannel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Locale') }}</label>
                                <select class="form-select" name="locale" required>
                                    @foreach($locales as $localeKey => $localeLabel)
                                        <option value="{{ $localeKey }}" @selected(($previewState['locale'] ?? '') === $localeKey)>{{ $localeLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Override email (optional)') }}</label>
                                <input type="email" class="form-control" name="test_email" placeholder="qa@example.com">
                            </div>
                            <div class="col-12">
                                <div class="notification-note-box">
                                    <div class="fw-semibold mb-2">{{ __('How each channel behaves') }}</div>
                                    <ul class="small text-muted mb-0 ps-3">
                                        <li>{{ __('Database test sends create an in-app notification for the linked order user.') }}</li>
                                        <li>{{ __('Email test sends use the override email when provided, otherwise the order customer email.') }}</li>
                                        <li>{{ __('WhatsApp test sends use the current order phone and the live provider configuration already set in admin.') }}</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="admin-form-actions">
                                    <div class="admin-form-actions-copy">
                                        <div class="admin-form-actions-title">{{ __('Ready for a controlled test?') }}</div>
                                        <div class="admin-form-actions-subtitle">{{ __('Send one admin-side test using the selected order, event, and channel so you can verify production behavior safely.') }}</div>
                                    </div>
                                    <div class="admin-form-actions-buttons">
                                        <button type="submit" class="btn btn-success d-inline-flex align-items-center gap-2" data-loading-text="{{ __('Sending test message...') }}"><i class="mdi mdi-send-outline"></i><span>{{ __('Send test message now') }}</span></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</details>
