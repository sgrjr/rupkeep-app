<div>
    <!-- Delete Button -->
    <button wire:click="confirmDelete" class="btn-base btn-action btn-action-danger w-full sm:w-auto">
        <x-svg-delete/>{!! isset($buttonText) ? $buttonText : 'Delete' !!}
    </button>

    <!-- Confirmation Modal -->
    @if (isset($confirmingDelete) && $confirmingDelete)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl w-80 max-w-sm mx-auto">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Confirm Deletion</h3>
                <p class="text-gray-700 mb-6">Are you sure you want to delete this item? This action cannot be undone.</p>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('confirmingDelete', false)" class="btn-base bg-gray-300 text-gray-800 hover:bg-gray-400">Cancel</button>
                    <button wire:click="delete" class="btn-base btn-action-danger">Confirm {!! $buttonText !!}</button>
                </div>
            </div>
        </div>
    @endif
</div>