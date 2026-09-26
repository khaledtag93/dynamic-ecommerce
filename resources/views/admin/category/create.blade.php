@extends('layouts.admin')

@section('title', __('Create Category'))

@section('content')
    <x-admin.page-header
        :kicker="__('Catalog management')"
        :title="__('Create Category')"
        :description="__('Add a new category with image, visibility, and SEO details.')"
    >
        <a href="{{ route('admin.categories.index') }}" class="btn btn-light admin-btn-soft admin-back-btn"><i class="mdi mdi-arrow-left"></i> {{ __('Back to Categories') }}</a>
    </x-admin.page-header>

    <form id="categoryEditorForm" action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data" data-submit-loading data-initial-dirty="{{ $errors->any() ? '1' : '0' }}">
        @csrf
        @php($submitLabel = __('Save Category'))
        @include('admin.category._form', ['category' => null, 'submitLabel' => $submitLabel])
    </form>
@endsection
