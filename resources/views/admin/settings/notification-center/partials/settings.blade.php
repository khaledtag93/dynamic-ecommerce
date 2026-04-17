<form action="{{ route('admin.settings.notifications.update') }}" method="POST">
    @csrf
    @method('PUT')

    <div id="notification-settings" class="notification-section-title mt-2">
        <div>
            <div class="notification-section-kicker">{{ __('Control panel') }}</div>
            <h3 class="admin-section-title mb-1">{{ __('Settings & channels') }}</h3>
            <p class="admin-section-subtitle mb-0">{{ __('Control the master switch, channel availability, and event rules so each customer touchpoint stays intentional and production-safe.') }}</p>
        </div>
        <span class="admin-chip">{{ __('Controls') }}</span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <h4 class="mb-1">{{ __('Global controls') }}</h4>
                    <div class="admin-helper-text mb-3">{{ __('Core switches for the notification engine and its delivery channels.') }}</div>
                    <div class="form-check form-switch notification-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="notification_center_enabled" name="notification_center_enabled" value="1" @checked(old('notification_center_enabled', $settings['notification_center_enabled'] ?? '1') === '1')>
                        <label class="form-check-label fw-semibold" for="notification_center_enabled">{{ __('Enable notification engine') }}</label>
                        <div class="admin-helper-text mt-2">{{ __('Turn the full notification engine on or off without interrupting the rest of the order workflow.') }}</div>
                    </div>

                    <div class="d-grid gap-3">
                        @foreach($channels as $channelKey => $channel)
                            <div class="rounded-4 border p-3 bg-white h-100">
                                <div class="form-check form-switch notification-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="notification_channel_{{ $channelKey }}_enabled" name="notification_channel_{{ $channelKey }}_enabled" value="1" @checked(old('notification_channel_'.$channelKey.'_enabled', $settings['notification_channel_'.$channelKey.'_enabled'] ?? ($channelKey === 'sms' ? '0' : '1')) === '1')>
                                    <label class="form-check-label fw-semibold" for="notification_channel_{{ $channelKey }}_enabled">{{ $channel['label'] }}</label>
                                </div>
                                <div class="admin-helper-text">{{ $channel['description'] }}</div>
                                @if($channelKey === 'whatsapp')
                                    <div class="admin-helper-text mt-2">{{ __('This channel still respects the WhatsApp production settings and approved template switches.') }}</div>
                                @elseif($channelKey === 'sms')
                                    <div class="admin-helper-text mt-2">{{ __('SMS is intentionally kept off until a provider is connected later.') }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <details class="notification-collapsible">
                <summary>
                    <span>{{ __('How the engine works') }}</span>
                    <span class="meta">{{ __('Reference guidance') }}</span>
                </summary>
                <div class="notification-collapsible-body">
                    <div class="admin-card border-0 shadow-none mb-0">
                        <div class="admin-card-body p-0">
                            <h4 class="mb-1">{{ __('How this works') }}</h4>
                            <div class="admin-helper-text mb-3">{{ __('A quick guide to how the engine, channels, and retries work together.') }}</div>
                    <ul class="mb-0 admin-helper-text d-grid gap-2 ps-3">
                        <li>{{ __('Global switch controls the whole engine.') }}</li>
                        <li>{{ __('Each channel can be enabled or disabled independently.') }}</li>
                        <li>{{ __('Each order event has its own per-channel toggle matrix.') }}</li>
                        <li>{{ __('Dispatch logs capture email, database, and WhatsApp hand-off results.') }}</li>
                        <li>{{ __('Retry center helps admins recover failed sends safely without touching code.') }}</li>
                    </ul>
                </div>
                        </div>
                    </div>
                </div>
            </details>
        </div>

        <div class="col-xl-8">
            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h4 class="mb-1">{{ __('Event × channel matrix') }}</h4>
                            <div class="admin-helper-text">{{ __('Choose exactly which channels should run for each order lifecycle event.') }}</div>
                        </div>
                        <span class="admin-chip">{{ __('Production-safe toggles') }}</span>
                    </div>

                    <div class="table-responsive admin-table-wrap">
                        <table class="table align-middle admin-table mb-0">
                            <thead>
                                <tr>
                                    <th style="min-width: 260px;">{{ __('Event') }}</th>
                                    @foreach($channels as $channelKey => $channel)
                                        <th class="text-center">{{ $channel['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($events as $eventKey => $event)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $event['label'] }}</div>
                                            <div class="admin-helper-text">{{ $event['description'] }}</div>
                                        </td>
                                        @foreach($channels as $channelKey => $channel)
                                            <td class="text-center">
                                                <div class="form-check form-switch notification-switch d-inline-flex justify-content-center m-0">
                                                    <input class="form-check-input" type="checkbox" id="notification_event_{{ $eventKey }}_{{ $channelKey }}" name="notification_event_{{ $eventKey }}_{{ $channelKey }}" value="1" @checked(old('notification_event_'.$eventKey.'_'.$channelKey, $settings['notification_event_'.$eventKey.'_'.$channelKey] ?? '0') === '1')>
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="notification-inline-section">
                <div class="notification-inline-section__head">
                    <div>
                        <h4 class="mb-1">{{ __('Recommended production setup') }}</h4>
                        <div class="admin-helper-text mb-0">{{ __('A practical baseline that keeps customer communication stable while Phase 4 continues growing.') }}</div>
                    </div>
                    <span class="admin-chip">{{ __('Recommended baseline') }}</span>
                </div>
                <div class="notification-mini-grid mb-4">
                    <div class="rounded-4 border p-3 h-100 bg-white">
                        <div class="fw-semibold mb-2">{{ __('Customer-safe baseline') }}</div>
                        <div class="admin-helper-text">{{ __('Keep database and email enabled for all key order events, then keep WhatsApp on for order status and delivery updates where templates are already approved.') }}</div>
                    </div>
                    <div class="rounded-4 border p-3 h-100 bg-white">
                        <div class="fw-semibold mb-2">{{ __('Operational visibility') }}</div>
                        <div class="admin-helper-text">{{ __('Use the logs below to review channel behavior, catch failures early, and request retries without breaking the active production flow.') }}</div>
                    </div>
                    <div class="rounded-4 border p-3 h-100 bg-white">
                        <div class="fw-semibold mb-2">{{ __('Recommended operator flow') }}</div>
                        <div class="admin-helper-text">{{ __('Save channel changes here first, then validate wording from Templates, and only after that handle live failures from Logs & retry.') }}</div>
                    </div>
                </div>

                    <div class="admin-form-actions mt-4">
                        <div class="admin-form-actions-copy">
                            <div class="admin-form-actions-title">{{ __('Ready to save these settings?') }}</div>
                            <div class="admin-form-actions-subtitle">{{ __('Review the channel toggles, recovery defaults, and operational settings, then save the notification center when you are ready.') }}</div>
                        </div>
                        <div class="admin-form-actions-buttons">
                            <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Saving notification center changes...') }}">{{ __('Save notification center changes') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
