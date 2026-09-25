@extends('layouts.public')
@section('content')
<section class="hero"><div class="container hero-grid">
<div class="hero-copy"><span class="eyebrow"><i></i> BEM-VINDO À FAMÍLIA GUNDJA</span>
<h1>{{ $settings['hero_title'] ?? 'Cuidamos de si. Fazemos parte do seu dia.' }}</h1>
<p>{{ $settings['hero_description'] ?? 'Da sua saúde ao seu negócio, estamos ao seu lado com atenção, proximidade e soluções para a vida.' }}</p>
<div class="button-row"><a class="button" href="/farmacia">Conheça a nossa farmácia <span>↗</span></a><a class="text-link" href="#segmentos">Explore os segmentos <span>↓</span></a></div>
<div class="hero-note"><span class="mini-cross">+</span><span>Saúde e bem-estar<br><strong>mais perto de si.</strong></span><span class="note-divider"></span><span>Presentes em<br><strong>Luanda e Bailundo</strong></span></div>
</div>
<div class="hero-art" aria-label="Ilustração de cuidado e bem-estar">
<div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
<div class="art-caption">CUIDAR É<br><em>da nossa natureza.</em></div>
<div class="bottle bottle-large"><div class="bottle-cap"></div><div class="bottle-label"><span class="bottle-plus">+</span><small>FAMÍLIA</small><strong>gundja</strong><hr><span>SAÚDE &<br>BEM-ESTAR</span><div class="leaf">⌁</div></div></div>
<div class="art-card"><span>✳</span><div>O cuidado começa<br><strong>com proximidade.</strong></div></div>
<span class="art-tag">A SUA FARMÁCIA. A SUA FAMÍLIA.</span>
</div>
</div></section>
<div class="values-strip"><div class="container"><span>✳ Atendimento próximo</span><span>+ Saúde e bem-estar</span><span>↗ Soluções para empresas</span><span>♡ Compromisso consigo</span></div></div>
<section class="section container"><div class="section-heading"><div><span class="eyebrow">O NOSSO PRIMEIRO CUIDADO</span><h2>Farmácia Gundja.<br><em>Perto de si, todos os dias.</em></h2></div><p>Um espaço de confiança para cuidar de si e da sua família. Encontre-nos em Luanda e no Bailundo.</p></div>
<div class="location-grid">@foreach($branches->filter(fn($b) => str_starts_with($b->slug, 'farmacia-')) as $branch)<a class="location-card" href="/farmacia/{{ str_replace('farmacia-', '', $branch->slug) }}"><span class="location-pin">⌖</span><div><small>FARMÁCIA GUNDJA</small><h3>{{ $branch->city }}</h3><p>{{ $branch->address ?: 'Conheça a nossa filial e os canais de atendimento.' }}</p></div><span class="circle-arrow">↗</span></a>@endforeach</div>
</section>
@if($products->isNotEmpty())<section class="section container"><div class="section-heading"><div><span class="eyebrow">ESCOLHIDOS PARA SI</span><h2>Em destaque</h2></div><a href="/produtos" class="text-link">Ver catálogo completo ↗</a></div><div class="product-grid">@foreach($products as $product)@include('frontend.product-card')@endforeach</div></section>@endif
<section class="section segments-section" id="segmentos"><div class="container"><div class="section-heading"><div><span class="eyebrow">UMA FAMÍLIA, VÁRIAS SOLUÇÕES</span><h2>Ao seu lado.<br><em>Em cada necessidade.</em></h2></div><a class="text-link" href="/segmentos">Conheça todos os negócios ↗</a></div>
<div class="segment-grid">@foreach($segments as $segment)<a class="segment-card segment-{{ $segment->slug }}" href="/{{ in_array($segment->slug, ['farmacia','comercial','timbragem','lubrificantes']) ? $segment->slug : 'segmentos/'.$segment->slug }}"><div class="segment-card-top">@if($segment->image)<div class="segment-card-logo-wrap"><img src="{{ Storage::disk('public')->url($segment->image) }}" alt="Logo {{ $segment->name }}" class="segment-card-logo"></div>@else<span class="segment-icon">{{ ['farmacia'=>'+','comercial'=>'↗','timbragem'=>'✳','lubrificantes'=>'◈'][$segment->slug] ?? '◇' }}</span>@endif<small>0{{ $loop->iteration }}</small></div><h3>{{ $segment->name }}</h3><p>{{ $segment->description }}</p><span class="segment-explore">Explorar <b>↗</b></span></a>@endforeach</div>
</div></section>
@if($banners->isNotEmpty())<section class="section container banners-section"><div class="section-heading"><div><span class="eyebrow">A NOSSA FAMÍLIA EM FOCO</span><h2>Campanhas e novidades</h2></div><span class="muted">Os nossos segmentos</span></div><div class="banners-grid">@foreach($banners as $banner)<div class="banner-card"><div class="banner-media"><img src="{{ Storage::disk('public')->url($banner->image) }}" alt="{{ $banner->alt }}" loading="lazy"></div><div class="banner-content"><small class="eyebrow">{{ $banner->segment?->name ?? 'FAMÍLIA GUNDJA' }}</small><h3>{{ $banner->title }}</h3><p>{{ $banner->description }}</p>@if($banner->target_url)<a class="button small" href="{{ $banner->target_url }}">{{ $banner->button_label ?: 'Saber mais' }} ↗</a>@endif</div></div>@endforeach</div></section>@endif
@if($posts->isNotEmpty())<section class="section container"><div class="section-heading"><div><span class="eyebrow">ACONTECE NA FAMÍLIA</span><h2>Novidades</h2></div><a href="/novidades" class="text-link">Todas as novidades ↗</a></div><div class="news-grid">@foreach($posts as $post)<a class="news-card" href="/novidades/{{ $post->slug }}">@if($post->image)<img src="{{ Storage::disk('public')->url($post->image) }}" alt="">@endif<small>{{ $post->published_at->format('d.m.Y') }}</small><h3>{{ $post->title }}</h3><p>{{ $post->excerpt }}</p></a>@endforeach</div></section>@endif
<section class="container section"><div class="contact-band"><div><span class="eyebrow">VAMOS CONVERSAR?</span><h2>A solução começa<br>com uma conversa.</h2></div><a href="/contacto" class="button light">Fale com a nossa equipa ↗</a></div></section>
@include('frontend.community')
@endsection
