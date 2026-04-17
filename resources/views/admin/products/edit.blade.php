@extends('layouts.admin')

@section('content')
    @livewire('admin.product.product-form', ['productId' => $product->id])
@endsection