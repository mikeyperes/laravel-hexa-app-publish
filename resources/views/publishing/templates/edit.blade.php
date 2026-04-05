{{-- Edit Template --}}
@extends('layouts.app')
@section('title', 'Edit ' . $template->name)
@section('header', 'Edit Template: ' . $template->name)

@section('content')
    @include('app-publish::publishing.templates.partials.form-card', [
        'form' => $form,
        'formValues' => $formValues,
        'template' => $template,
        'mode' => 'edit',
        'cancelUrl' => route('publish.templates.show', $template->id),
    ])
@endsection
