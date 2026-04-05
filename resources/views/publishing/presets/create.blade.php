{{-- Create WordPress Preset --}}
@extends('layouts.app')
@section('title', 'Create WordPress Preset')
@section('header', 'Create WordPress Preset')

@section('content')
    @include('app-publish::publishing.presets.partials.form-card', [
        'form' => $form,
        'formValues' => $formValues,
        'mode' => 'create',
        'cancelUrl' => route('publish.presets.index'),
    ])
@endsection
