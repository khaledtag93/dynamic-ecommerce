<div>
    <button wire:click="testClick">Test Click</button>

    <div class="modal fade" id="deleteModel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Test Modal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Clicked? {{ $clicked ? 'Yes' : 'No' }}</p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        window.addEventListener('openDeleteModal', () => {
            console.log('openDeleteModal event received');
            const modal = new bootstrap.Modal(document.getElementById('deleteModel'));
            modal.show();
        });
    </script>
    @endpush
</div>
