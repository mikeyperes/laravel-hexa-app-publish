{{-- Create Template --}}
@extends('layouts.app')
@section('title', 'Create Article Preset')
@section('header', 'Create Article Preset')

@section('content')
    @include('app-publish::publishing.templates.partials.form-card', [
        'form' => $form,
        'formValues' => $formValues,
        'mode' => 'create',
        'cancelUrl' => route('publish.templates.index'),
    ])
@endsection
