@extends('layouts.app')

@section('title', 'Editar usuário')

@section('content')
    <div class="mx-auto max-w-4xl">
        <div class="mb-6"><h2 class="text-2xl font-bold text-slate-950">{{ $user->name }}</h2><p class="mt-1 text-sm text-slate-600">Atualize perfil, situação ou senha.</p></div>
        <form method="POST" action="{{ route('users.update', $user) }}" class="rounded-2xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            @method('PUT')
            @include('users._form')
        </form>
    </div>
@endsection
