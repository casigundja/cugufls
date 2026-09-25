<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Segment;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Services\OrderService;
use App\Services\PublicCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FrontendController extends Controller
{
    private function shared(): array
    {
        return ['segments' => Segment::where('active', true)->orderBy('sort_order')->get(),
            'branches' => Branch::where('active', true)->get(), 'settings' => Setting::pluck('value', 'key')->all(),
            'faqs' => Faq::where('active', true)->whereNull('segment_id')->orderBy('sort_order')->get(),
            'testimonials' => Testimonial::where('active', true)->whereNotNull('consented_at')->whereNull('segment_id')->orderBy('sort_order')->get(),
            'team' => TeamMember::where('active', true)->whereNull('segment_id')->orderBy('sort_order')->get()];
    }

    public function home()
    {
        return view('frontend.home', [...$this->shared(), 'products' => Product::where('active', true)->where('featured', true)->whereHas('category', fn ($q) => $q->where('active', true))->whereHas('segment', fn ($q) => $q->where('active', true))->with('productImages', 'segment')->limit(4)->get(),
            'posts' => Post::where('status', 'published')->where('published_at', '<=', now())->latest('published_at')->limit(3)->get(),
            'banners' => Banner::where('active', true)->where('placement', 'homepage')->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))->orderBy('sort_order')->get()]);
    }

    public function segment(Request $request, string $segment, ?string $section = null)
    {
        $entity = Segment::where('slug', $segment)->where('active', true)->firstOrFail();
        if ($section === 'produtos') {
            return $this->products($request, $entity);
        }
        $branch = in_array($section, ['luanda', 'bailundo']) ? $entity->branches()->where('slug', 'farmacia-'.$section)->where('active', true)->firstOrFail() : null;

        return view('frontend.segment', [...$this->shared(), 'segment' => $entity, 'section' => $section, 'branch' => $branch,
            'services' => Service::where('segment_id', $entity->id)->where('active', true)->get(),
            'products' => app(PublicCatalog::class)->products($request, $entity->id)->limit(6)->get(),
            'faqs' => Faq::where('active', true)->where('segment_id', $entity->id)->orderBy('sort_order')->get(),
            'testimonials' => Testimonial::where('active', true)->whereNotNull('consented_at')->where('segment_id', $entity->id)->orderBy('sort_order')->get(),
            'team' => TeamMember::where('active', true)->where('segment_id', $entity->id)->orderBy('sort_order')->get(),
            'banners' => Banner::where('active', true)->where('placement', 'segment')->where('segment_id', $entity->id)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))->orderBy('sort_order')->get()]);
    }

    public function products(Request $request, ?Segment $segment = null)
    {
        return view('frontend.products', [...$this->shared(), 'segment' => $segment,
            'products' => app(PublicCatalog::class)->products($request, $segment?->id)->paginate(12)->withQueryString(),
            'categories' => Category::where('active', true)->when($segment, fn ($q) => $q->where('segment_id', $segment->id))->get()]);
    }

    public function product(Request $request, string $slug)
    {
        $product = app(PublicCatalog::class)->products($request)->where('slug', $slug)->firstOrFail();

        return view('frontend.product', [...$this->shared(), 'product' => $product, 'locations' => $product->segment->branches()->where('active', true)->get(), 'key' => (string) Str::uuid()]);
    }

    public function page(Request $request, string $page = 'sobre')
    {
        if ($page === 'novidades') {
            return view('frontend.posts', [...$this->shared(), 'posts' => Post::where('status', 'published')->where('published_at', '<=', now())->latest('published_at')->paginate(12)]);
        }
        $content = Page::where('slug', $page)->where('status', 'published')->where('published_at', '<=', now())->first();
        if (! in_array($page, ['sobre', 'segmentos', 'contacto']) && ! $content) {
            abort(404);
        }

        return view('frontend.page', [...$this->shared(), 'page' => $page, 'content' => $content]);
    }

    public function post(string $slug)
    {
        $post = Post::where('slug', $slug)->where('status', 'published')->where('published_at', '<=', now())->firstOrFail();

        return view('frontend.article', [...$this->shared(), 'post' => $post]);
    }

    public function contact(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:150'], 'email' => ['nullable', 'required_without:phone', 'email', 'max:254'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:20'], 'company' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'], 'type' => ['required', Rule::in(['general', 'demo', 'quote'])],
            'segment_id' => ['nullable', 'integer', Rule::exists('segments', 'id')->where('active', true)], 'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('active', true)], 'website' => ['nullable', 'max:0']]);
        if (! empty($data['branch_id'])) {
            abort_unless(! empty($data['segment_id']) && Branch::findOrFail($data['branch_id'])->segments()->whereKey($data['segment_id'])->exists(), 422);
        }
        unset($data['website']);
        (new Contact)->forceFill([...$data, 'status' => 'new'])->save();

        return $request->expectsJson() ? response()->json(['data' => ['accepted' => true]], 202) : back()->with('success', 'Mensagem recebida. A nossa equipa dará seguimento ao seu contacto.');
    }

    public function order(StoreOrderRequest $request, OrderService $service)
    {
        $key = $request->header('Idempotency-Key') ?: $request->input('idempotency_key');
        validator(['key' => $key], ['key' => ['required', 'uuid']])->validate();
        $data = $request->validated();
        unset($data['idempotency_key']);
        $order = $service->create($data, $key, $request->is('api/*') ? 'api' : 'website');
        $receipt = $service->receipt($order);
        if ($request->expectsJson()) {
            return response()->json(['data' => $receipt], $order->wasRecentlyCreated ? 201 : 200);
        }
        $request->session()->put('order_receipt', $receipt);

        return redirect('/pedidos/confirmacao');
    }

    public function confirmation(Request $request)
    {
        abort_unless($request->session()->has('order_receipt'), 404);

        return view('frontend.confirmation', [...$this->shared(), 'receipt' => $request->session()->get('order_receipt')]);
    }
}
