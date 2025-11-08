@props(['deleteId'])

<dialog 
    x-ref="deleteModal" 
    x-show="showDeleteModal" 
    @click.self="showDeleteModal = false" 
    @close-delete-modal.window="showDeleteModal = false"
    class="modal" 
    x-init="$watch('showDeleteModal', value => { if (value) { $refs.deleteModal.showModal() } else { $refs.deleteModal.close() } })">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-2">Delete Academic Paper</h3>
        <p class="text-sm text-base-content/70 mb-4">Are you sure?</p>
        <p class="mb-6">Are you sure you want to delete this academic paper? This action cannot be undone.</p>
        
        <div class="modal-action">
            <button @click="showDeleteModal = false" class="btn">Cancel</button>
            <button 
                wire:click="performDelete({{ $deleteId }})"
                class="btn btn-error" 
                wire:loading.attr="disabled"
                wire:target="performDelete">
                <span wire:loading.remove wire:target="performDelete">Delete</span>
                <span wire:loading wire:target="performDelete" class="loading loading-spinner loading-sm"></span>
            </button>
        </div>
    </div>
</dialog>
