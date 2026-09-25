@extends('layouts.public')
@section('title', $post->title.' — Família Gundja')
@section('content')<article class="container section"><div class="breadcrumbs"><a href="/novidades">Novidades</a> / {{ $post->published_at->format('d.m.Y') }}</div><h1>{{ $post->title }}</h1>@if($post->image)<img style="max-height:480px;max-width:100%;border-radius:16px;margin-bottom:35px" src="{{ Storage::disk('public')->url($post->image) }}" alt="">@endif<p>{{ $post->excerpt }}</p><div class="body-copy">{{ $post->content }}</div></article>@endsection
