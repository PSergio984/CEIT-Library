@props(['selectedPaper', 'isAdmin' => true])

<dialog 
    x-ref="paperModal" 
    wire:ignore.self
    @click.self="showPaperModal = false" 
    @close="if(showPaperModal) { showPaperModal = false }"
    class="modal backdrop-blur"
    x-effect="showPaperModal ? (!$refs.paperModal.open && $refs.paperModal.showModal()) : ($refs.paperModal.open && $refs.paperModal.close())">
    <div class="modal-box w-11/12 max-w-5xl" 
        x-show="showPaperModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.stop>
        <form method="dialog">
            <button @click="showPaperModal = false" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        
        <x-academic-paper-detail-modal :selectedPaper="$selectedPaper" :isAdmin="$isAdmin" />
        
        <div class="modal-action">
            <button @click="showPaperModal = false" class="btn btn-primary">Close</button>
        </div>
    </div>
</dialog>
