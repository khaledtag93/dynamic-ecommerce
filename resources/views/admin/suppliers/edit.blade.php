@extends('layouts.admin')

@section('title', __('Edit Supplier'))

@section('content')
<x-admin.page-header :kicker="__('Procurement')" :title="__('Edit Supplier')" :description="__('Update supplier details without affecting existing orders or purchase history.')">
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light border btn-text-icon"><i class="mdi mdi-arrow-left"></i><span>{{ __('Back to Suppliers') }}</span></a>
</x-admin.page-header>
<div class="admin-page-shell">
@if(session('success'))<div class="alert alert-success border-0 shadow-sm rounded-4 mb-0">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger border-0 shadow-sm rounded-4 mb-0">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger border-0 shadow-sm rounded-4 mb-0">{{ __('Please review the form and fix the highlighted fields.') }}</div>@endif
<form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}">@csrf
@method('PUT')
@php($submitLabel = __('Save Supplier'))
@include('admin.suppliers._form', ['supplier' => $supplier, 'submitLabel' => $submitLabel])
</form>
</div>
@endsection
