<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Família Gundja — Cuidamos de si')</title>
<meta name="description" content="@yield('description', 'Saúde, comércio, personalização e lubrificantes. Conheça a Família Gundja, em Luanda e no Bailundo.')">
<link rel="canonical" href="{{ url()->current() }}"><link rel="icon" href="/favicon.svg" type="image/svg+xml">
@vite('resources/js/app.js')
</head>
<body class="public-site"><a class="skip-link" href="#main">Saltar para o conteúdo</a>
<div class="topline"><div class="container"><span>Uma família. Muitas formas de cuidar.</span><span>Luanda & Bailundo <span class="dot"></span> Angola</span></div></div>
@php
    $currentSegmentSlug = null;
    if (isset($segment) && $segment instanceof \App\Models\Segment) {
        $currentSegmentSlug = $segment->slug;
    } elseif (request()->segment(1) && in_array(request()->segment(1), ['farmacia', 'comercial', 'timbragem', 'lubrificantes'])) {
        $currentSegmentSlug = request()->segment(1);
    }

    $logoMap = [
        'farmacia' => ['src' => '/images/segments/farmacia-logo.png', 'alt' => 'Farmácia Gundja', 'url' => '/farmacia'],
        'comercial' => ['src' => '/images/segments/comercial-logo.png', 'alt' => 'Gundja Comercial', 'url' => '/comercial'],
        'timbragem' => ['src' => '/images/segments/timbragem-logo.png', 'alt' => 'Gundja Timbragem', 'url' => '/timbragem'],
        'lubrificantes' => ['src' => '/images/segments/lubrificantes-logo.png', 'alt' => 'Gundja Lubrificantes', 'url' => '/lubrificantes'],
    ];

    $headerLogo = $currentSegmentSlug && isset($logoMap[$currentSegmentSlug])
        ? $logoMap[$currentSegmentSlug]
        : ['src' => '/images/branding/logo-cugufls.jpg', 'alt' => 'CUGUFLS', 'url' => '/'];
@endphp
<header class="site-header"><div class="container header-inner">
<a href="{{ $headerLogo['url'] }}" class="brand" aria-label="{{ $headerLogo['alt'] }}"><img src="{{ $headerLogo['src'] }}" alt="{{ $headerLogo['alt'] }}" class="site-brand-logo {{ $currentSegmentSlug ? 'segment-header-logo' : '' }}"></a>
<button type="button" class="mobile-toggle" data-menu-button aria-label="Abrir navegação">☰</button>
<nav class="public-nav" data-mobile-menu aria-label="Navegação principal">
<a href="/farmacia" class="{{ request()->is('farmacia*') ? 'selected' : '' }}">Farmácia</a>
<a href="/comercial">Comercial</a><a href="/timbragem">Timbragem</a><a href="/lubrificantes">Lubrificantes</a><a href="/sobre">A nossa família</a>
<a href="/contacto" class="button small">Fale connosco <span>↗</span></a>
</nav></div></header>
@if(session('success'))<div class="container notice">{{ session('success') }}</div>@endif
@if($errors->any())<div class="container error-box" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<main id="main">@yield('content')</main>
<footer class="site-footer"><div class="container footer-grid">
<div><a href="/" class="brand" aria-label="CUGUFLS"><img src="/images/branding/logo-cugufls.jpg" alt="CUGUFLS" class="site-brand-logo footer-logo"></a><p>Próximos de si.<br>Comprometidos com o seu dia a dia.</p></div>
<div><h3>Os nossos negócios</h3><a href="/farmacia">Farmácia Gundja</a><a href="/comercial">Gundja Comercial</a><a href="/timbragem">Gundja Timbragem</a><a href="/lubrificantes">Gundja Lubrificantes</a></div>
<div><h3>Conheça-nos</h3><a href="/sobre">A nossa história</a><a href="/novidades">Novidades</a><a href="/contacto">Contactos</a><a href="/login">Área administrativa ↗</a>@foreach(['facebook'=>'Facebook','instagram'=>'Instagram'] as $social => $label)@if(!empty($settings[$social]) && str_starts_with($settings[$social], 'https://'))<a href="{{ $settings[$social] }}" rel="noopener" target="_blank">{{ $label }} ↗</a>@endif @endforeach</div>
<div><h3>Estamos perto de si</h3><p>Luanda · Bailundo<br>Angola</p><a href="/farmacia/contacto">Encontrar uma farmácia ↗</a></div>
</div><div class="container footer-bottom"><span>© {{ date('Y') }} CUGUFLS / Família Gundja</span><span>Feito para cuidar. Feito para servir.</span></div></footer>
</body></html>
