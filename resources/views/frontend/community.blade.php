@if(isset($faqs) && $faqs->isNotEmpty())
<section class="container section"><div class="section-heading"><div><span class="eyebrow">PODEMOS AJUDAR</span><h2>Perguntas frequentes</h2></div></div>@foreach($faqs as $faq)<details class="access-card"><summary>{{ $faq->question }}</summary><p class="body-copy">{{ $faq->answer }}</p></details>@endforeach</section>
@endif
@if(isset($testimonials) && $testimonials->isNotEmpty())
<section class="container section"><div class="section-heading"><h2>Quem está connosco</h2></div><div class="news-grid">@foreach($testimonials as $testimonial)<blockquote class="news-card"><p>{{ $testimonial->content }}</p><strong>{{ $testimonial->name }}</strong></blockquote>@endforeach</div></section>
@endif
@if(isset($team) && $team->isNotEmpty())
<section class="container section"><div class="section-heading"><h2>A nossa equipa</h2></div><div class="news-grid">@foreach($team as $member)<article class="news-card">@if($member->image)<img src="{{ Storage::disk('public')->url($member->image) }}" alt="{{ $member->name }}" loading="lazy">@endif<h3>{{ $member->name }}</h3><p>{{ $member->position }}</p><p>{{ $member->bio }}</p></article>@endforeach</div></section>
@endif
