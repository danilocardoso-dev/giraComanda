@extends('layouts.app')

@section('title', 'Novo usuário')

@section('content')
    <div class="mx-auto max-w-4xl">
        <div class="mb-6"><h2 class="text-2xl font-bold text-slate-950">Cadastrar usuário</h2><p class="mt-1 text-sm text-slate-600">Defina o perfil de acesso da pessoa.</p></div>
        <form method="POST" action="{{ route('users.store') }}" class="rounded-2xl border border-brand-100 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            @include('users._form')
        </form>
    </div>
@endsection
