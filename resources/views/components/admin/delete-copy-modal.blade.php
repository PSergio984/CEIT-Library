@props(['copyToDelete'])

<dialog 
    x-ref="copyDeleteModal" 
    x-show="showCopyDeleteModal"
    @click.self="showCopyDeleteModal = false"
    @close="showCopyDeleteModal = false"
    @close-copy-delete-modal.window="showCopyDeleteModal = false"
    class="modal"
    x-init="$watch('showCopyDeleteModal', value => { if (value) { $refs.copyDeleteModal.showModal() } else { $refs.copyDeleteModal.close() } })"
>
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-2">Delete Copy</h3>
        <p class="text-sm text-base-content/70 mb-4">Are you sure?</p>
        @if($copyToDelete)
            <p class="mb-6">Are you sure you want to delete copy #<span class="font-semibold text-error">{{ $copyToDelete }}</span>? This action cannot be undone. Only available copies can be deleted.</p>
        @else
            <p class="mb-6">Are you sure you want to delete this copy? This action cannot be undone. Only available copies can be deleted.</p>
        @endif
        <div class="modal-action">
            <button @click="showCopyDeleteModal = false" class="btn">Cancel</button>
            <button 
                wire:click="performCopyDelete({{ $copyToDelete }})"
                class="btn btn-error"
                wire:loading.attr="disabled"
                wire:target="performCopyDelete">
                <span wire:loading.remove wire:target="performCopyDelete">Delete Copy</span>
                <span wire:loading wire:target="performCopyDelete" class="loading loading-spinner loading-sm"></span>
            </button>
        </div>
    </div>
</dialog>
