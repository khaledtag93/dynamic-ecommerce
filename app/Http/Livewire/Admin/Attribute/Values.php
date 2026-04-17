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
        $this->attributeId = $id;
    }

    public function save()
    {
        $this->validate();

        ProductAttributeValue::updateOrCreate(
            ['id' => $this->valueId],
            [
                'attribute_id' => $this->attributeId,
                'value' => trim($this->value),
            ]
        );

        $this->reset(['value', 'valueId']);
        session()->flash('message', 'Value saved successfully.');
    }

    public function edit($id)
    {
        $item = ProductAttributeValue::findOrFail($id);

        $this->valueId = $item->id;
        $this->value = $item->value;
    }

    public function delete($id)
    {
        ProductAttributeValue::findOrFail($id)->delete();
        session()->flash('message', 'Value deleted successfully.');
    }

    public function render()
    {
        $attribute = ProductAttribute::with('values')->findOrFail($this->attributeId);

        return view('livewire.admin.attribute.values', compact('attribute'))
            ->layout('layouts.admin');
    }
}
