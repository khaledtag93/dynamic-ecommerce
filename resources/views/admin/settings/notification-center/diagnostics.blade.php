@extends('admin.settings.notification-center.layout')

@section('notification-module-content')
    @include('admin.settings.notification-center.partials.monitoring')
    @include('admin.settings.notification-center.partials.deep-diagnostics')
@endsection
