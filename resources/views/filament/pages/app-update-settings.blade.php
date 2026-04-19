<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Lưu cấu hình
        </x-filament::button>
    </form>
</x-filament-panels::page>
