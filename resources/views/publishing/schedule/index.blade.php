@extends('layouts.app')
@section('title', $pageTitle)
@section('header', $pageHeader)

@section('content')
@include('calendar::ui.manager', ['calendarConfig' => $calendarConfig])
@endsection
