import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

if (document.getElementById('app') && document.querySelector('script[data-page="app"]')) {
    const pages = import.meta.glob('./Pages/**/*.vue');
    createInertiaApp({
        resolve: async name => (await pages['./Pages/'+name+'.vue']()).default,
        setup({ el, App, props, plugin }) { createApp({ render: () => h(App, props) }).use(plugin).mount(el); },
        progress: { color: '#24664c' },
    });
}
document.querySelector('[data-menu-button]')?.addEventListener('click', () => {
    const menu = document.querySelector('[data-mobile-menu]');
    menu?.classList.toggle('open');
    document.querySelector('[data-menu-button]')?.setAttribute('aria-expanded', menu?.classList.contains('open') ? 'true' : 'false');
});
document.querySelectorAll('form[method="post"]').forEach(form => form.addEventListener('submit', () => {
    const submit = form.querySelector('button:not([type="button"])');
    if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
}));
