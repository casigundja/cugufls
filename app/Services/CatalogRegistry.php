<?php

namespace App\Services;

use App\Models\User;

class CatalogRegistry
{
    public function get(string $resource): array
    {
        return config('catalog.'.$resource) ?? abort(404);
    }

    public function navigation(User $user): array
    {
        $access = app(Access::class);
        $items = [['title' => 'Visão geral', 'group' => 'Principal', 'href' => '/admin/dashboard']];
        foreach (config('catalog') as $slug => $resource) {
            $permission = $resource['permission'].'.view';
            if ($access->global($user, $permission) || $user->userAccesses()->whereHas('role.permissions', fn ($q) => $q->where('name', $permission))->exists()) {
                $items[] = ['title' => $resource['title'], 'group' => $resource['group'], 'href' => '/admin/'.$slug];
            }
        }
        if ($access->global($user, 'reports.stock') || $user->userAccesses()->whereHas('role.permissions', fn ($q) => $q->where('name', 'like', 'reports.%'))->exists()) {
            $items[] = ['title' => 'Relatórios', 'group' => 'Negócio', 'href' => '/admin/relatorios'];
        }
        if ($access->any($user, 'products.import') || $access->any($user, 'stock.import')) {
            $items[] = ['title' => 'Importar produtos', 'group' => 'Catálogo', 'href' => '/admin/importacoes'];
        }
        if ($user->hasRole('super-admin')) {
            $items[] = ['title' => 'Perfis e acessos', 'group' => 'Sistema', 'href' => '/admin/acessos'];
            $items[] = ['title' => 'Configurações', 'group' => 'Sistema', 'href' => '/admin/configuracoes'];
        }
        $items[] = ['title' => 'Segurança', 'group' => 'Conta', 'href' => '/admin/seguranca'];
        if ($access->any($user, 'products.view') || $access->any($user, 'content.view')) {
            $items[] = ['title' => 'Biblioteca de imagens', 'group' => 'Website', 'href' => '/admin/imagens'];
        }
        $items[] = ['title' => 'Notificações', 'group' => 'Conta', 'href' => '/admin/notificacoes'];

        return $items;
    }
}
