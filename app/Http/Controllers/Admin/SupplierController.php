<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status'),
            'sort' => (string) $request->string('sort', 'updated_at'),
            'direction' => strtolower((string) $request->string('direction', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        $sortMap = [
            'name' => 'name',
            'company' => 'company',
            'email' => 'email',
            'phone' => 'phone',
            'items_count' => 'items_count',
            'purchases_count' => 'purchases_count',
            'is_active' => 'is_active',
            'updated_at' => 'updated_at',
        ];

        $sortColumn = $sortMap[$filters['sort']] ?? 'updated_at';

        $suppliers = Supplier::query()
            ->withCount(['items', 'purchases'])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] !== '', function ($query) use ($filters) {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy($sortColumn, $filters['direction'])
            ->when($sortColumn !== 'updated_at', fn ($query) => $query->orderByDesc('updated_at'))
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => Supplier::count(),
            'active' => Supplier::where('is_active', true)->count(),
            'inactive' => Supplier::where('is_active', false)->count(),
            'with_purchases' => Supplier::has('purchases')->count(),
        ];

        return view('admin.suppliers.index', compact('suppliers', 'filters', 'stats'));
    }

    public function create() { return view('admin.suppliers.create', ['supplier' => new Supplier()]); }
    public function edit(Supplier $supplier) { return view('admin.suppliers.edit', compact('supplier')); }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']) . '-' . Str::lower(Str::random(5));
        Supplier::create($data);
        return redirect()->route('admin.suppliers.index')->with('success', __('Supplier created successfully.'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));
        return redirect()->route('admin.suppliers.index')->with('success', __('Supplier updated successfully.'));
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchases()->exists()) {
            return back()->with('error', __('This supplier already has purchases and cannot be deleted.'));
        }

        $supplier->delete();
        return back()->with('success', __('Supplier deleted successfully.'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
