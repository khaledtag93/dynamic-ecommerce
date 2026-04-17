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

    protected $queryString = [
        'search' => ['except' => ''],
        'visibility' => ['except' => ''],
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

    public function delete($id)
    {
        $brand = Brand::find($id);

        if ($brand) {
            if ($brand->products()->exists()) {
                session()->flash('error', __('This brand is linked to products and cannot be deleted.'));
                return;
            }
            $brand->delete();
            session()->flash('message', __('Brand deleted successfully.'));
            $this->resetPage();
        }
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
            });

        return view('livewire.admin.brand.index', [
            'brands' => $query->latest('id')->paginate(10),
            'stats' => [
                'total' => Brand::count(),
                'visible' => Brand::where('status', 0)->count(),
                'hidden' => Brand::where('status', 1)->count(),
                'linked' => Brand::has('products')->count(),
            ],
        ])->layout('layouts.admin');
    }
}
