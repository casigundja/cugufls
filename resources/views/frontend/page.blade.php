@extends('layouts.public')
@section('title', ($content?->seo_title ?? $content?->title ?? ucfirst($page)).' — Família Gundja')
@section('description', $content?->seo_description ?? 'Conheça a Família Gundja e os nossos canais de atendimento.')
@section('content')
<section class="page-hero"><div class="container"><span class="eyebrow">FAMÍLIA GUNDJA</span><h1>{{ $content?->title ?? ['sobre'=>'Uma família que cuida.','segmentos'=>'Os nossos negócios.','contacto'=>'Vamos conversar.'][$page] }}</h1></div></section>
<section class="container section">
@if($content)<div class="body-copy">{{ $content->content }}</div>
@elseif($page === 'contacto')<div class="detail-grid"><div><h2>Estamos ao seu lado.</h2><p>Fale connosco sobre produtos, serviços ou soluções para o seu negócio.</p>@if($settings['contact_phone'] ?? null)<p><a href="tel:{{ $settings['contact_phone'] }}">{{ $settings['contact_phone'] }}</a></p>@endif @if($settings['contact_email'] ?? null)<p><a href="mailto:{{ $settings['contact_email'] }}">{{ $settings['contact_email'] }}</a></p>@endif<h3>Onde estamos</h3>@foreach($branches as $branch)<p><strong>{{ $branch->name }}</strong><br>{{ $branch->address ?: $branch->city }}@if($branch->phone)<br>{{ $branch->phone }}@endif</p>@endforeach</div><div>@include('frontend.contact-form')</div></div>
@elseif($page === 'sobre')<div class="detail-grid"><div><span class="eyebrow">CUIDAR. SERVIR. ESTAR PERTO.</span><h2>Uma presença próxima.<br><em>Um compromisso consigo.</em></h2></div><div class="body-copy"><p>{{ $settings['about'] ?? 'A Família Gundja reúne diferentes áreas de atividade com um propósito comum: estar próxima das pessoas e dos seus negócios. Da Farmácia ao Comercial, da Timbragem aos Lubrificantes, encontre soluções para o seu dia a dia.' }}</p><a class="text-link" href="/segmentos">Conheça os nossos segmentos ↗</a></div></div>
@else<div class="segment-grid">@foreach($segments as $segment)<a class="segment-card segment-{{ $segment->slug }}" href="/segmentos/{{ $segment->slug }}"><div class="segment-card-top">@if($segment->image)<div class="segment-card-logo-wrap"><img src="{{ Storage::disk('public')->url($segment->image) }}" alt="Logo {{ $segment->name }}" class="segment-card-logo"></div>@else<span class="segment-icon">✳</span>@endif<small>0{{ $loop->iteration }}</small></div><h3>{{ $segment->name }}</h3><p>{{ $segment->description }}</p><span class="segment-explore">Explorar <b>↗</b></span></a>@endforeach</div>@endif
</section>
@include('frontend.community')
@endsection
