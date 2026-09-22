<?php

namespace App\Livewire\Concerns;

use App\Enums\EligibilityField;
use App\Enums\EligibilityOperator;
use App\Enums\FieldType;
use App\Enums\ScholarshipCategory;
use App\Models\ScholarshipProgram;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The application-form builder, shared by the two screens that use it: the
 * scholarship program's template and an offering's working copy.
 *
 * Everything here goes through the owner's `formFields()` and
 * `eligibilityRules()` relations, so the same code drives either level
 * without knowing which table it is writing to.
 */
trait ConfiguresApplicationForm
{
    public array $fields = [];

    public ?string $fieldsSuccessMessage = null;

    public string $required_gwa = '';

    public bool $showPreview = false;

    /** The scholarship program or offering whose configuration is being edited. */
    abstract protected function configurationOwner(): Model;

    /** The program this configuration belongs to — its own, or the offering's. */
    abstract protected function owningProgram(): ScholarshipProgram;

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
        return $this->owningProgram()->category === ScholarshipCategory::Academic;
    }

    public function requiresIncome(): bool
    {
        return $this->configurationOwner()->eligibilityRules()
            ->where('field', EligibilityField::Income->value)
            ->exists();
    }

    protected function loadFields(): void
    {
        $this->fields = $this->configurationOwner()->formFields()->orderBy('sort_order')->get()->map(fn ($field) => [
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
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    public function moveFieldUp(int $index): void
    {
        if ($index > 0) {
            [$this->fields[$index - 1], $this->fields[$index]] = [$this->fields[$index], $this->fields[$index - 1]];
        }
    }

    public function moveFieldDown(int $index): void
    {
        if ($index < count($this->fields) - 1) {
            [$this->fields[$index + 1], $this->fields[$index]] = [$this->fields[$index], $this->fields[$index + 1]];
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

        $this->validate($rules);

        $keptIds = array_values(array_filter(array_column($this->fields, 'id')));

        if (($blocked = $this->fieldsBlockingRemoval($keptIds)) !== []) {
            $this->addError('fields', 'Remove the answers first, or keep these fields: '.implode(', ', $blocked).'.');

            return;
        }

        $owner = $this->configurationOwner();

        DB::transaction(function () use ($owner): void {
            $savedIds = [];

            foreach ($this->fields as $index => $field) {
                $type = FieldType::from($field['field_type']);

                $record = $owner->formFields()->updateOrCreate(['id' => $field['id']], [
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

            $owner->formFields()->whereNotIn('id', $savedIds)->delete();

            if ($this->isAcademic()) {
                $owner->eligibilityRules()->updateOrCreate(
                    ['field' => EligibilityField::Gwa->value],
                    [
                        'operator' => EligibilityOperator::Gte->value,
                        'value' => $this->required_gwa,
                        'message' => "Your GWA does not meet the required {$this->required_gwa} for this scholarship.",
                    ]
                );
            }
        });

        $this->loadFields();
        $this->fieldsSuccessMessage = 'Requirements saved.';
    }

    /**
     * Labels of fields the save would delete but cannot, because applicants
     * have already answered them. Only offerings can hit this — a program
     * template is never answered directly.
     *
     * @param  array<int, int>  $keptIds  ids still present in the editor
     * @return array<int, string>
     */
    protected function fieldsBlockingRemoval(array $keptIds): array
    {
        return $this->configurationOwner()->formFields()
            ->whereNotIn('id', $keptIds)
            ->get()
            ->filter(fn ($field) => method_exists($field, 'hasResponses') && $field->hasResponses())
            ->pluck('label')
            ->all();
    }
}
