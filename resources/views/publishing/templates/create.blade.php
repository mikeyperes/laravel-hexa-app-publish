{{-- Create Template --}}
@extends('layouts.app')
@section('title', 'Create Template')
@section('header', 'Create Article Template')

@section('content')
    @include('app-publish::publishing.templates.partials.form-card', [
        'form' => $form,
        'formValues' => $formValues,
        'mode' => 'create',
        'cancelUrl' => route('publish.templates.index'),
    ])
@endsection
