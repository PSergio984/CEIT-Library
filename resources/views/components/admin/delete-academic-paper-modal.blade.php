@props(['deleteId'])

<dialog 
    x-ref="deleteModal" 
    wire:ignore.self
    @click.self="showDeleteModal = false" 
    @close="if(showDeleteModal) { showDeleteModal = false }"
    @close-delete-modal.window="showDeleteModal = false"
    class="modal"
    x-effect="showDeleteModal ? (!$refs.deleteModal.open && $refs.deleteModal.showModal()) : ($refs.deleteModal.open && $refs.deleteModal.close())">
    <div class="modal-box"
        x-show="showDeleteModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95">
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
