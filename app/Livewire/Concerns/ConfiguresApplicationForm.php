<?php

namespace App\Livewire\Concerns;

use App\Enums\EligibilityField;
use App\Enums\EligibilityOperator;
use App\Enums\FieldType;
use App\Enums\ScholarshipCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The application-form builder used by an offering's Requirements page and
 * the Standard requirements page.
 *
 * Fields go through `formFieldsQuery()`; the required GWA through the
 * offering's `eligibilityRules()`, so only an academic offering has one.
 */
trait ConfiguresApplicationForm
{
    public array $fields = [];

    public string $required_gwa = '';

    public bool $showPreview = false;

    /** The field whose settings are expanded in the builder, by index. */
    public ?int $editingField = null;

    /** The offering whose form is being edited — null for the standard form. */
    abstract protected function configurationOwner(): ?Model;

    protected function formFieldsQuery(): Builder|HasMany
    {
        return $this->configurationOwner()->formFields();
    }

    /** Runs after the form is saved. */
    protected function fieldsSaved(): void {}

    protected function bootConfiguration(): void
    {
        $this->loadFields();

        if ($this->isAcademic()) {
            $this->required_gwa = (string) ($this->configurationOwner()->eligibilityRules()
                ->where('field', EligibilityField::Gwa->value)
                ->value('value') ?? '');
        }
    }

    public function isAcademic(): bool
    {
        return $this->configurationOwner()?->category === ScholarshipCategory::Academic;
    }

    protected function loadFields(): void
    {
        $this->fields = $this->formFieldsQuery()->orderBy('sort_order')->get()->map(fn ($field) => [
            'id' => $field->id,
            'field_type' => $field->field_type->value,
            'label' => $field->label,
            'description' => (string) $field->description,
            'options' => (string) $field->options,
            'is_required' => $field->is_required,
            'requires_physical_copy' => $field->requires_physical_copy,
            'accepted_formats' => $field->accepted_formats ?? 'pdf,jpg,png',
            'max_size_mb' => $field->max_size_mb === null ? '5' : (string) $field->max_size_mb,
        ])->all();
    }

    protected function blankField(): array
    {
        return [
            'id' => null,
            'field_type' => 'text',
            'label' => '',
            'description' => '',
            'options' => '',
            'is_required' => true,
            'requires_physical_copy' => false,
            'accepted_formats' => 'pdf,jpg,png',
            'max_size_mb' => '5',
        ];
    }

    public function addField(): void
    {
        $this->fields[] = $this->blankField();
        $this->editingField = count($this->fields) - 1;
    }

    public function toggleField(int $index): void
    {
        $this->editingField = $this->editingField === $index ? null : $index;
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);

        // Keep the open editor on the same field after the list shifts.
        if ($this->editingField === $index) {
            $this->editingField = null;
        } elseif ($this->editingField !== null && $this->editingField > $index) {
            $this->editingField--;
        }
    }

    public function moveFieldUp(int $index): void
    {
        if ($index > 0) {
            $this->swapFields($index, $index - 1);
        }
    }

    public function moveFieldDown(int $index): void
    {
        if ($index < count($this->fields) - 1) {
            $this->swapFields($index, $index + 1);
        }
    }

    protected function swapFields(int $a, int $b): void
    {
        [$this->fields[$a], $this->fields[$b]] = [$this->fields[$b], $this->fields[$a]];

        if ($this->editingField === $a) {
            $this->editingField = $b;
        } elseif ($this->editingField === $b) {
            $this->editingField = $a;
        }
    }

    public function updatedFields($value, $key): void
    {
        if (! str_ends_with($key, '.field_type')) {
            return;
        }

        $index = (int) explode('.', $key)[0];
        $type = FieldType::from($value);

        if ($type->needsOptions() && trim($this->fields[$index]['options'] ?? '') === '') {
            $this->fields[$index]['options'] = "Option 1\nOption 2\nOption 3";
        }

        if ($type->isFile()) {
            $current = trim($this->fields[$index]['accepted_formats'] ?? '');

            if ($current === '' || $current === 'pdf,jpg,png' || $current === 'jpg,jpeg,png') {
                $this->fields[$index]['accepted_formats'] = $type->defaultAcceptedFormats();
            }
        }
    }

    public function saveFields(): void
    {
        $rules = [
            'fields' => ['array'],
            'fields.*.field_type' => ['required', 'in:'.implode(',', array_column(FieldType::cases(), 'value'))],
            'fields.*.label' => ['required', 'string', 'max:150'],
            'fields.*.description' => ['nullable', 'string'],
        ];

        if ($this->isAcademic()) {
            $rules['required_gwa'] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        foreach ($this->fields as $index => $field) {
            $type = FieldType::from($field['field_type']);

            if ($type->needsOptions()) {
                $rules["fields.{$index}.options"] = [
                    'required', 'string',
                    function ($attribute, $value, $fail): void {
                        if (array_filter(array_map('trim', explode("\n", $value))) === []) {
                            $fail('Add at least one choice.');
                        }
                    },
                ];
            }

            if ($type->isFile()) {
                $rules["fields.{$index}.accepted_formats"] = ['required', 'string', 'max:100'];
                $rules["fields.{$index}.max_size_mb"] = ['required', 'integer', 'min:1'];
            }
        }

        try {
            $this->validate($rules);
        } catch (ValidationException $exception) {
            $this->dispatch('notice', tone: 'error', title: 'Application form not saved', message: 'Fix the fields marked in red, then save again.');

            throw $exception;
        }

        $keptIds = array_values(array_filter(array_column($this->fields, 'id')));

        if (($blocked = $this->fieldsBlockingRemoval($keptIds)) !== []) {
            $this->dispatch('notice', tone: 'error', title: 'Application form not saved', message: 'Applicants already answered these fields, so keep them: '.implode(', ', $blocked).'.');

            return;
        }

        DB::transaction(function (): void {
            $savedIds = [];

            foreach ($this->fields as $index => $field) {
                $type = FieldType::from($field['field_type']);

                $record = $this->formFieldsQuery()->updateOrCreate(['id' => $field['id']], [
                    'field_type' => $field['field_type'],
                    'label' => $field['label'],
                    'description' => $field['description'] ?: null,
                    'options' => $type->needsOptions() ? $field['options'] : null,
                    'is_required' => $field['is_required'],
                    'requires_physical_copy' => $type->isFile() ? $field['requires_physical_copy'] : false,
                    'accepted_formats' => $type->isFile() ? $field['accepted_formats'] : null,
                    'max_size_mb' => $type->isFile() ? $field['max_size_mb'] : null,
                    'sort_order' => $index + 1,
                ]);

                $savedIds[] = $record->id;
            }

            $this->formFieldsQuery()->whereNotIn('id', $savedIds)->delete();

            if ($this->isAcademic()) {
                $this->configurationOwner()->eligibilityRules()->updateOrCreate(
                    ['field' => EligibilityField::Gwa->value],
                    [
                        'operator' => EligibilityOperator::Gte->value,
                        'value' => $this->required_gwa,
                        'message' => "You need a GWA of at least {$this->required_gwa} to apply for this scholarship.",
                    ]
                );
            }
        });

        $this->fieldsSaved();
        $this->loadFields();
        $this->editingField = null;
        $this->dispatch('notice', title: 'Application form saved', message: count($this->fields).' '.str('field')->plural(count($this->fields)));
    }

    /**
     * Labels of fields the save would delete but cannot, because applicants
     * have already answered them. Only offerings can hit this — the standard
     * form is never answered directly.
     *
     * @param  array<int, int>  $keptIds  ids still present in the editor
     * @return array<int, string>
     */
    protected function fieldsBlockingRemoval(array $keptIds): array
    {
        return $this->formFieldsQuery()
            ->whereNotIn('id', $keptIds)
            ->get()
            ->filter(fn ($field) => method_exists($field, 'hasResponses') && $field->hasResponses())
            ->pluck('label')
            ->all();
    }
}
