@props(['selectedPaper', 'isAdmin' => true])

<dialog 
    x-ref="paperModal" 
    x-show="showPaperModal"
    @click.self="showPaperModal = false" 
    @close="showPaperModal = false" 
    class="modal"
  
    x-init="$watch('showPaperModal', value => { if (value) { $refs.paperModal.showModal() } else { $refs.paperModal.close() } })">
    <div class="modal-box w-11/12 max-w-5xl">
        <form method="dialog">
            <button @click="showPaperModal = false" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        
        <x-academic-paper-detail-modal :selectedPaper="$selectedPaper" :isAdmin="$isAdmin" />
        
        <div class="modal-action">
            <button @click="showPaperModal = false" class="btn btn-primary">Close</button>
        </div>
    </div>
</dialog>
