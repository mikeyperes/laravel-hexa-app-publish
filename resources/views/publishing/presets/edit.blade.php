{{-- Edit WordPress Preset --}}
@extends('layouts.app')
@section('title', 'Edit Preset: ' . $preset->name)
@section('header', 'Edit Preset: ' . $preset->name)

@section('content')
    @include('app-publish::publishing.presets.partials.form-card', [
        'form' => $form,
        'formValues' => $formValues,
        'preset' => $preset,
        'mode' => 'edit',
        'cancelUrl' => route('publish.presets.index', request()->only('user_id')),
    ])
@endsection
