<?php

namespace App\Http\Livewire\Admin\Brand;

use App\Models\Brand;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $name = '';
    public $slug = '';
    public $status = false;
    public $brandIdToEdit = null;
    public $search = '';
    public $visibility = '';
    public $usage = '';
    public $perPage = 10;
    public $pendingDeleteId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'visibility' => ['except' => ''],
        'usage' => ['except' => ''],
        'perPage' => ['except' => 10],
    ];

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:brands,slug,' . $this->brandIdToEdit,
            'status' => 'boolean',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingVisibility()
    {
        $this->resetPage();
    }

    public function updatingUsage()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'visibility', 'usage', 'perPage']);
        $this->perPage = 10;
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->reset(['name', 'slug', 'status', 'brandIdToEdit']);
        $this->resetValidation();
    }

    public function saveBrand()
    {
        $this->validate();

        Brand::updateOrCreate(
            ['id' => $this->brandIdToEdit],
            [
                'name' => $this->name,
                'slug' => $this->slug,
                'status' => $this->status ? 1 : 0,
            ]
        );

        session()->flash('message', $this->brandIdToEdit ? __('Brand updated successfully.') : __('Brand added successfully.'));

        $this->resetForm();
    }

    public function edit($id)
    {
        $brand = Brand::findOrFail($id);
        $this->brandIdToEdit = $brand->id;
        $this->name = $brand->name;
        $this->slug = $brand->slug;
        $this->status = (bool) $brand->status;

    }

    public function requestDelete($id): void
    {
        $brand = Brand::withCount('products')->findOrFail($id);

        if ($brand->products_count > 0) {
            session()->flash('error', __('This brand is linked to products and cannot be deleted.'));
            return;
        }

        $this->pendingDeleteId = $brand->id;
        $this->dispatchBrowserEvent('open-brand-delete-confirmation', [
            'id' => $brand->id,
            'name' => $brand->name,
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

        $brand = Brand::withCount('products')->find($this->pendingDeleteId);

        if (! $brand) {
            $this->pendingDeleteId = null;
            session()->flash('error', __('Brand no longer exists.'));
            return;
        }

        if ($brand->products_count > 0) {
            $this->pendingDeleteId = null;
            session()->flash('error', __('This brand is linked to products and cannot be deleted.'));
            return;
        }

        $brandName = $brand->name;
        $brand->delete();
        $this->pendingDeleteId = null;
        $this->resetPage();

        session()->flash('message', __('Brand :name deleted successfully.', ['name' => $brandName]));
    }

    public function render()
    {
        $query = Brand::query()
            ->withCount('products')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('name', 'like', '%' . trim($this->search) . '%')
                        ->orWhere('slug', 'like', '%' . trim($this->search) . '%');
                });
            })
            ->when($this->visibility !== '', function ($query) {
                $query->where('status', $this->visibility === 'hidden' ? 1 : 0);
            })
            ->when($this->usage === 'linked', fn ($query) => $query->has('products'))
            ->when($this->usage === 'empty', fn ($query) => $query->doesntHave('products'));

        return view('livewire.admin.brand.index', [
            'brands' => $query->latest('id')->paginate($this->perPage),
            'stats' => [
                'total' => Brand::count(),
                'visible' => Brand::where('status', 0)->count(),
                'hidden' => Brand::where('status', 1)->count(),
                'linked' => Brand::has('products')->count(),
                'empty' => Brand::doesntHave('products')->count(),
            ],
        ])->layout('layouts.admin');
    }
}
