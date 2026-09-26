<?php

namespace App\Http\Livewire\Admin\Attribute;

use App\Models\ProductAttribute;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $name = '';
    public $editingId = null;
    public $search = '';
    public $coverage = '';
    public $perPage = 10;
    public $pendingDeleteId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'coverage' => ['except' => ''],
        'perPage' => ['except' => 10],
    ];

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_attributes', 'name')->ignore($this->editingId),
            ],
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCoverage()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'coverage', 'perPage']);
        $this->perPage = 10;
        $this->resetPage();
    }

    public function save()
    {
        $this->validate();

        ProductAttribute::updateOrCreate(
            ['id' => $this->editingId],
            ['name' => $this->name]
        );

        session()->flash('message', $this->editingId ? 'Attribute updated successfully.' : 'Attribute added successfully.');

        $this->resetForm();
        $this->resetPage();
    }

    public function edit($id)
    {
        $attr = ProductAttribute::findOrFail($id);
        $this->editingId = $attr->id;
        $this->name = $attr->name;
    }

    public function requestDelete($id): void
    {
        $attribute = ProductAttribute::withCount(['values', 'variantAttributes'])->findOrFail($id);

        if ($attribute->variant_attributes_count > 0) {
            session()->flash('error', __('This attribute is used by product variants and cannot be deleted.'));
            return;
        }

        $this->pendingDeleteId = $attribute->id;
        $this->dispatch(
            'open-attribute-delete-confirmation',
            name: $attribute->name,
            values: $attribute->values_count,
        );
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

        $attribute = ProductAttribute::withCount('variantAttributes')->find($this->pendingDeleteId);

        if (! $attribute) {
            $this->pendingDeleteId = null;
            $this->dispatch('close-attribute-delete-confirmation');
            session()->flash('error', __('Attribute no longer exists.'));
            return;
        }

        if ($attribute->variant_attributes_count > 0) {
            $this->pendingDeleteId = null;
            $this->dispatch('close-attribute-delete-confirmation');
            session()->flash('error', __('This attribute is used by product variants and cannot be deleted.'));
            return;
        }

        $name = $attribute->name;
        $attribute->delete();
        $this->pendingDeleteId = null;
        $this->resetPage();
        $this->dispatch('close-attribute-delete-confirmation');

        session()->flash('message', __('Attribute :name deleted successfully.', ['name' => $name]));
    }

    public function resetForm()
    {
        $this->reset(['name', 'editingId']);
        $this->resetValidation();
    }

    public function render()
    {
        $query = ProductAttribute::query()
            ->withCount('values')
            ->withCount('variantAttributes')
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', '%' . trim($this->search) . '%');
            })
            ->when($this->coverage === 'with_values', fn ($query) => $query->has('values'))
            ->when($this->coverage === 'empty', fn ($query) => $query->doesntHave('values'))
            ->when($this->coverage === 'in_use', fn ($query) => $query->has('variantAttributes'));

        return view('livewire.admin.attribute.index', [
            'attributes' => $query->latest()->paginate($this->perPage),
            'stats' => [
                'total' => ProductAttribute::count(),
                'with_values' => ProductAttribute::has('values')->count(),
                'values_total' => \App\Models\ProductAttributeValue::count(),
                'empty' => ProductAttribute::doesntHave('values')->count(),
                'in_use' => ProductAttribute::has('variantAttributes')->count(),
            ],
        ])->layout('layouts.admin');
    }
}
