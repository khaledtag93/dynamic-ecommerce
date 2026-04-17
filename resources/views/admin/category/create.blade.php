@extends('layouts.admin')

@section('title', __('Create Category'))

@section('content')
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">{{ __('Create Category') }}</h1>
            <p class="admin-page-description">{{ __('Add a new category with image, visibility, and SEO details.') }}</p>
        </div>

        <div class="admin-page-actions">
            <a href="{{ route('admin.categories.index') }}" class="btn btn-light admin-btn-soft admin-back-btn"><i class="mdi mdi-arrow-left"></i> {{ __('Back to Categories') }}</a>
        </div>
    </div>

    <form action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @php($submitLabel = __('Save Category'))
        @include('admin.category._form', ['category' => null, 'submitLabel' => $submitLabel])
    </form>
@endsection
