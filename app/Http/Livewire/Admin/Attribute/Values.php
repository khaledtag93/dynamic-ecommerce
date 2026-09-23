<?php

namespace App\Http\Livewire\Admin\Attribute;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Values extends Component
{
    public $attributeId;
    public $value = '';
    public $valueId = null;
    public $search = '';
    public $pendingDeleteId = null;

    public function rules()
    {
        return [
            'value' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_attribute_values', 'value')
                    ->where(fn ($query) => $query->where('attribute_id', $this->attributeId))
                    ->ignore($this->valueId),
            ],
        ];
    }

    public function mount($id)
    {
        ProductAttribute::findOrFail($id);
        $this->attributeId = $id;
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
    }

    public function resetForm(): void
    {
        $this->reset(['value', 'valueId']);
        $this->resetValidation();
    }

    public function save()
    {
        $this->validate();

        $existingValue = $this->valueId
            ? ProductAttributeValue::query()
                ->where('attribute_id', $this->attributeId)
                ->findOrFail($this->valueId)
            : null;

        if ($existingValue && $existingValue->value !== trim($this->value) && $this->variantUsageCount($existingValue) > 0) {
            $this->addError('value', __('This value is already used by product variants and cannot be renamed.'));
            return;
        }

        ProductAttributeValue::updateOrCreate(
            [
                'id' => $this->valueId,
                'attribute_id' => $this->attributeId,
            ],
            [
                'value' => trim($this->value),
            ]
        );

        $this->resetForm();
        session()->flash('message', __('Value saved successfully.'));
    }

    public function edit($id)
    {
        $item = ProductAttributeValue::query()
            ->where('attribute_id', $this->attributeId)
            ->findOrFail($id);

        $this->valueId = $item->id;
        $this->value = $item->value;
    }

    protected function variantUsageCount(ProductAttributeValue $value): int
    {
        return \App\Models\ProductVariantAttribute::query()
            ->where('attribute_id', $this->attributeId)
            ->where('attribute_value', $value->value)
            ->count();
    }

    public function requestDelete($id): void
    {
        $value = ProductAttributeValue::query()
            ->where('attribute_id', $this->attributeId)
            ->findOrFail($id);

        $usageCount = $this->variantUsageCount($value);

        if ($usageCount > 0) {
            session()->flash('error', __('This value is used by :count product variant(s) and cannot be deleted.', ['count' => $usageCount]));
            return;
        }

        $this->pendingDeleteId = $value->id;
        $this->dispatchBrowserEvent('open-attribute-value-delete-confirmation', [
            'value' => $value->value,
        ]);
    }

    public function cancelDelete(): void
    {
        $this->pendingDeleteId = null;
    }

    public function confirmDelete(): void
    {
        if (! $this->pendingDeleteId) {
            return;
        }

        $value = ProductAttributeValue::query()
            ->where('attribute_id', $this->attributeId)
            ->find($this->pendingDeleteId);

        if (! $value) {
            $this->pendingDeleteId = null;
            session()->flash('error', __('Attribute value no longer exists.'));
            return;
        }

        $usageCount = $this->variantUsageCount($value);

        if ($usageCount > 0) {
            $this->pendingDeleteId = null;
            session()->flash('error', __('This value is used by :count product variant(s) and cannot be deleted.', ['count' => $usageCount]));
            return;
        }

        $label = $value->value;
        $value->delete();
        $this->pendingDeleteId = null;

        session()->flash('message', __('Value :value deleted successfully.', ['value' => $label]));
    }

    public function render()
    {
        $attribute = ProductAttribute::findOrFail($this->attributeId);

        $values = ProductAttributeValue::query()
            ->where('attribute_id', $this->attributeId)
            ->when($this->search !== '', fn ($query) => $query->where('value', 'like', '%' . trim($this->search) . '%'))
            ->orderBy('value')
            ->get()
            ->map(function (ProductAttributeValue $value) {
                $value->variant_usage_count = $this->variantUsageCount($value);
                return $value;
            });

        $allValues = ProductAttributeValue::query()
            ->where('attribute_id', $this->attributeId)
            ->get()
            ->map(function (ProductAttributeValue $value) {
                $value->variant_usage_count = $this->variantUsageCount($value);
                return $value;
            });

        $stats = [
            'total' => $allValues->count(),
            'in_use' => $allValues->where('variant_usage_count', '>', 0)->count(),
            'unused' => $allValues->where('variant_usage_count', 0)->count(),
        ];

        return view('livewire.admin.attribute.values', compact('attribute', 'values', 'stats'))
            ->layout('layouts.admin');
    }
}
