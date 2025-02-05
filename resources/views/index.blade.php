@extends('statamic::layout')
@section('title', __('Bulk Editor'))

@section('content')
    <header class="mb-6">
        @include('statamic::partials.breadcrumb', [
            'url' => cp_route('utilities.index'),
            'title' => __('Utilities')
        ])
        <h1>{{ __('Bulk Editor') }}</h1>
    </header>

    <publish-form
        title="Settings"
        instructions="For each collection, define the fields that may be edited in bulk."
        action="{{ cp_route('utilities.cn-bulk-editor.update') }}"
        :blueprint='@json($blueprint)'
        :meta='@json($meta)'
        :values='@json($values)'
    ></publish-form>
@stop
