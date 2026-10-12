<?php

namespace App\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Email = 'email';
    case Tel = 'tel';
    case Url = 'url';
    case Date = 'date';
    case Time = 'time';
    case DateTime = 'datetime-local';
    case Month = 'month';
    case Week = 'week';
    case Color = 'color';
    case Range = 'range';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Radio = 'radio';
    case File = 'file';
    case Image = 'image';

    public function label(): string
    {
        return match ($this) {
            self::DateTime => 'Date & time',
            self::Tel => 'Phone number',
            self::Url => 'Website URL',
            self::Image => 'Image upload',
            self::File => 'File upload',
            default => str($this->value)->title(),
        };
    }

    /**
     * The icon the form builder shows beside a field of this type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Text => 'type',
            self::Textarea => 'paragraph',
            self::Number => 'hash',
            self::Email => 'mail',
            self::Tel => 'phone',
            self::Url => 'link',
            self::Date, self::Month, self::Week => 'calendar',
            self::Time, self::DateTime => 'clock',
            self::Color, self::Range => 'sliders',
            self::Select, self::Radio => 'list',
            self::Checkbox => 'check-square',
            self::File => 'upload',
            self::Image => 'image',
        };
    }

    public function needsOptions(): bool
    {
        return $this === self::Select || $this === self::Radio;
    }

    public function isFile(): bool
    {
        return $this === self::File || $this === self::Image;
    }

    public function isImage(): bool
    {
        return $this === self::Image;
    }

    public function defaultAcceptedFormats(): string
    {
        return $this->isImage() ? 'jpg,jpeg,png' : 'pdf,jpg,png';
    }
}
