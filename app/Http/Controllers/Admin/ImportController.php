<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index()
    {
        $jobs = ImportJob::latest('id')->paginate(20);
        return view('admin.imports.index', compact('jobs'));
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

        return back()->with('success', 'Import job draft created successfully.');
    }
}
