<x-filament-widgets::widget>
    <x-filament::section heading="Reply">
        @if($this->isConversationOpen())
            <form wire:submit="send" class="space-y-4">
                <div>
                    <textarea
                        wire:model="replyBody"
                        rows="4"
                        placeholder="Type your reply..."
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                    ></textarea>
                    @error('replyBody')
                        <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove>Send Reply</span>
                        <span wire:loading>Sending...</span>
                    </x-filament::button>
                </div>
            </form>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">This conversation is closed. No further replies can be sent.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
