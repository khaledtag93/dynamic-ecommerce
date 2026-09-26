<?php

namespace App\Http\Livewire\Admin\Attribute;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Values extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $attributeId;
    public $value = '';
    public $valueId = null;
    public $search = '';
    public $pendingDeleteId = null;
    public $perPage = 25;

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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
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
        $this->dispatch('open-attribute-value-delete-confirmation', value: $value->value);
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
            $this->dispatch('close-attribute-value-delete-confirmation');
            session()->flash('error', __('Attribute value no longer exists.'));
            return;
        }

        $usageCount = $this->variantUsageCount($value);

        if ($usageCount > 0) {
            $this->pendingDeleteId = null;
            $this->dispatch('close-attribute-value-delete-confirmation');
            session()->flash('error', __('This value is used by :count product variant(s) and cannot be deleted.', ['count' => $usageCount]));
            return;
        }

        $label = $value->value;
        $value->delete();
        $this->pendingDeleteId = null;
        $this->dispatch('close-attribute-value-delete-confirmation');

        session()->flash('message', __('Value :value deleted successfully.', ['value' => $label]));
    }

    public function render()
    {
        $attribute = ProductAttribute::findOrFail($this->attributeId);

        $search = mb_substr(trim((string) $this->search), 0, 100);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = "%{$escaped}%";

        $values = ProductAttributeValue::query()
            ->select('product_attribute_values.*')
            ->selectSub(function ($query) {
                $query->from('product_variant_attributes')
                    ->whereColumn('product_variant_attributes.attribute_id', 'product_attribute_values.attribute_id')
                    ->whereColumn('product_variant_attributes.attribute_value', 'product_attribute_values.value')
                    ->selectRaw('COUNT(*)');
            }, 'variant_usage_count')
            ->where('attribute_id', $this->attributeId)
            ->when($search !== '', fn ($query) => $query->where('value', 'like', $like))
            ->orderBy('value')
            ->paginate($this->perPage);

        $baseValues = ProductAttributeValue::query()
            ->where('attribute_id', $this->attributeId);

        $inUse = (clone $baseValues)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('product_variant_attributes')
                    ->whereColumn('product_variant_attributes.attribute_id', 'product_attribute_values.attribute_id')
                    ->whereColumn('product_variant_attributes.attribute_value', 'product_attribute_values.value');
            })
            ->count();

        $total = (clone $baseValues)->count();

        $stats = [
            'total' => $total,
            'in_use' => $inUse,
            'unused' => max(0, $total - $inUse),
        ];

        return view('livewire.admin.attribute.values', compact('attribute', 'values', 'stats'))
            ->layout('layouts.admin');
    }
}
