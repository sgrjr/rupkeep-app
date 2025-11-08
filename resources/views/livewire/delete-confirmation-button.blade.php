<div>
    <!-- Delete Button -->
    <button wire:click="confirmDelete" class="btn-base btn-action btn-action-danger w-full sm:w-auto">
        <x-svg-delete/>{!! $buttonText ?? 'Delete' !!}
    </button>

    <!-- Confirmation Modal -->
    @if (isset($confirmingDelete) && $confirmingDelete)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl w-80 max-w-sm mx-auto">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    {{ $force ? 'Confirm Permanent Delete' : 'Confirm Deletion' }}
                </h3>
                <p class="text-gray-700 mb-6">
                    {{ $force
                        ? 'This will permanently delete the record and all related data. This action cannot be undone.'
                        : 'This will archive the record. You can restore it later from the vehicles list.' }}
                </p>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('confirmingDelete', false)" class="btn-base bg-gray-300 text-gray-800 hover:bg-gray-400">Cancel</button>
                    <button wire:click="delete" class="btn-base btn-action-danger">
                        {{ __('Confirm :label', ['label' => $buttonText ?? __('Delete')]) }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>