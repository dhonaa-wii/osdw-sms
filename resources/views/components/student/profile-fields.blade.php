{{-- The personal-detail inputs bound to the EditsStudentProfile trait. --}}
<div {{ $attributes->merge(['class' => 'grid grid-cols-1 gap-4 sm:grid-cols-2']) }}>
    <x-ui.input name="last_name" label="Last name" wire:model="last_name" autocomplete="family-name" required />
    <x-ui.input name="first_name" label="First name" wire:model="first_name" autocomplete="given-name" required />
    <x-ui.input name="middle_name" label="Middle name" wire:model="middle_name" autocomplete="additional-name" />
    <x-ui.input name="suffix" label="Suffix" wire:model="suffix" placeholder="Jr., III" />
    <x-ui.select name="sex" label="Sex" wire:model="sex" placeholder="Select" required>
        @foreach (\App\Enums\Sex::cases() as $option)
            <option value="{{ $option->value }}">{{ $option->label() }}</option>
        @endforeach
    </x-ui.select>
    <x-ui.input name="birthdate" label="Birthdate" type="date" wire:model="birthdate" autocomplete="bday" />
    <x-ui.input name="mobile" label="Mobile number" type="tel" wire:model="mobile" autocomplete="tel" placeholder="09XX XXX XXXX" />
    <x-ui.textarea name="address" label="Address" wire:model="address" rows="2" />
</div>
