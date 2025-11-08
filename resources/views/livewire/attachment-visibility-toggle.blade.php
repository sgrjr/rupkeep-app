<label class="inline-flex items-center gap-2 text-xs text-gray-600">
    <input type="checkbox"
           wire:model="isPublic"
           class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
    <span>
        {{ $isPublic ? __('Public to customer') : __('Staff only') }}
    </span>
</label>

