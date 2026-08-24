<x-filament-widgets::widget>
    <x-filament::section heading="Order Notes">
        @php $notes = $this->getNotes(); @endphp

        <div class="space-y-4">
            @forelse ($notes as $note)
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
                    <p class="text-sm text-gray-950 dark:text-white whitespace-pre-line">{{ $note->note }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $note->user?->name ?? 'System' }}
                        &middot;
                        {{ $note->created_at->format('M j, Y g:i A') }}
                    </p>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No notes yet.</p>
            @endforelse
        </div>

        <form wire:submit="addNote" class="mt-4 flex items-start gap-2">
            <div class="flex-1">
                {{ $this->form }}
            </div>
            <x-filament::button type="submit">
                Add Note
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-widgets::widget>
