<div>
    <button type="button" class="btn btn-primary btn-text-icon" data-bs-toggle="modal" data-bs-target="#brandModal">
        <i class="mdi mdi-plus-circle-outline"></i>
        <span>{{ __('Add brand') }}</span>
    </button>

    <div wire:ignore.self class="modal fade" id="brandModal" tabindex="-1" aria-labelledby="brandModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content admin-card">
                <form wire:submit.prevent="storeBrand">
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <h5 class="modal-title fw-bold" id="brandModalLabel">{{ __('Brand details') }}</h5>
                            <p class="text-muted small mb-0">{{ __('Create a reusable catalog brand with a clean slug and visibility status.') }}</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Name') }}</label>
                            <input type="text" wire:model="name" class="form-control" />
                            @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Slug') }}</label>
                            <input type="text" wire:model="slug" class="form-control" />
                            @error('slug') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="admin-section-card p-3">
                            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                <div>
                                    <div class="fw-semibold">{{ __('Visibility') }}</div>
                                    <div class="text-muted small">{{ __('Turn this on to hide the brand from customer-facing flows.') }}</div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input type="checkbox" wire:model="status" class="form-check-input" id="brandStatusModal" />
                                    <label class="form-check-label" for="brandStatusModal">{{ __('Hide brand') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Save brand') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
  window.addEventListener('close-modal', () => {
    const modalEl = document.getElementById('brandModal');
    if (modalEl) {
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) {
        modal.hide();
      }
    }
  });
</script>
@endpush
