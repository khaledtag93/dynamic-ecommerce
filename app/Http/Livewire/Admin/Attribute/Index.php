<?php

namespace App\Http\Livewire\Admin\Attribute;

use App\Models\ProductAttribute;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $name = '';
    public $editingId = null;
    public $search = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    protected $rules = [
        'name' => 'required|string|max:255',
    ];

    public function updatingSearch()
    {
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

    public function delete($id)
    {
        ProductAttribute::findOrFail($id)->delete();
        session()->flash('message', 'Attribute deleted successfully.');
        $this->resetPage();
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
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', '%' . trim($this->search) . '%');
            });

        return view('livewire.admin.attribute.index', [
            'attributes' => $query->latest()->paginate(10),
            'stats' => [
                'total' => ProductAttribute::count(),
                'with_values' => ProductAttribute::has('values')->count(),
                'values_total' => \App\Models\ProductAttributeValue::count(),
            ],
        ])->layout('layouts.admin');
    }
}
