<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index(Request $request)
    {
        $jobs = ImportJob::latest('id')->paginate(20)->withQueryString();

        if ($request->header('X-Live-List') === '1') {
            return response()->view('admin.imports._results', compact('jobs'));
        }

        $stats = [
            'total' => ImportJob::count(),
            'draft' => ImportJob::where('status', 'draft')->count(),
            'named_files' => ImportJob::whereNotNull('file_name')->where('file_name', '!=', '')->count(),
        ];

        return view('admin.imports.index', compact('jobs', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:255'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'column_mapping' => ['nullable', 'string'],
        ]);

        ImportJob::create([
            'type' => $data['type'],
            'file_name' => $data['file_name'] ?? null,
            'status' => 'draft',
            'column_mapping' => $data['column_mapping'] ? json_decode($data['column_mapping'], true) : null,
            'meta' => ['future_ready' => true],
        ]);

        return back()->with('success', __('Import job draft created successfully.'));
    }
}
