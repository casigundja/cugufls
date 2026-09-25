<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Branch;
use App\Models\Segment;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $names = ['dashboard.view', 'products.cost.view', 'products.import', 'stock.import', 'stock.transfer', 'transfers.dispatch', 'transfers.receive',
            'orders.cancel', 'orders.deliver', 'roles.manage', 'permissions.manage', 'users.assign_roles', 'settings.view', 'settings.update',
            'reports.stock', 'reports.movements', 'reports.orders', 'reports.products', 'reports.segments', 'reports.branches', 'content.publish', 'api.tokens.manage'];
        foreach (config('catalog') as $definition) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $names[] = $definition['permission'].'.'.$action;
            }
        }
        $names = array_values(array_unique($names));
        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $all = Permission::where('guard_name', 'web')->pluck('name')->all();
        Role::findOrCreate('super-admin', 'web')->syncPermissions($all);
        Role::findOrCreate('admin', 'web')->syncPermissions(array_values(array_diff($all, ['roles.manage', 'permissions.manage', 'users.assign_roles', 'logs.view', 'logs.create', 'logs.update', 'logs.delete', 'settings.view', 'settings.update', 'api.tokens.manage'])));
        $common = ['dashboard.view', 'products.view', 'categories.view', 'brands.view', 'segments.view', 'branches.view'];
        $stock = ['stock.view', 'stock.update', 'stock.transfer', 'stock.import', 'movements.view', 'transfers.view', 'transfers.dispatch', 'transfers.receive', 'reports.stock', 'reports.movements'];
        $sales = ['orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'orders.deliver', 'customers.view', 'customers.create', 'customers.update', 'contacts.view', 'contacts.update', 'reports.orders'];
        Role::findOrCreate('manager', 'web')->syncPermissions(array_unique([...$common, ...$stock, ...$sales, 'products.create', 'products.update', 'products.delete', 'products.import', 'products.cost.view', 'categories.create', 'categories.update', 'categories.delete', 'services.view', 'services.create', 'services.update', 'services.delete', 'reports.products', 'reports.segments', 'reports.branches']));
        Role::findOrCreate('stock-manager', 'web')->syncPermissions([...$common, ...$stock]);
        Role::findOrCreate('sales-manager', 'web')->syncPermissions([...$common, ...$sales]);
        Role::findOrCreate('employee', 'web')->syncPermissions([...$common, 'stock.view', 'orders.view', 'orders.create', 'orders.update', 'customers.view', 'customers.create']);
        Role::findOrCreate('content-manager', 'web')->syncPermissions([...$common, 'content.view', 'content.create', 'content.update', 'content.delete', 'content.publish', 'services.view', 'services.create', 'services.update', 'services.delete', 'team.view', 'team.create', 'team.update', 'team.delete']);

        $segmentDefinitions = [
            'farmacia' => ['Farmácia Gundja', 'Saúde e bem-estar mais perto de si.', 'segments/farmacia-logo.png'],
            'comercial' => ['Gundja Comercial', 'Soluções e produtos para o seu negócio.', 'segments/comercial-logo.png'],
            'timbragem' => ['Gundja Timbragem', 'A sua marca, em cada detalhe.', 'segments/timbragem-logo.png'],
            'lubrificantes' => ['Gundja Lubrificantes', 'Cuidado e desempenho para o seu veículo.', 'segments/lubrificantes-logo.png'],
        ];

        foreach ($segmentDefinitions as $index => $values) {
            $segment = Segment::firstOrNew(['slug' => $index]);
            $segment->forceFill([
                'name' => $values[0],
                'slug' => $index,
                'description' => $values[1],
                'image' => $values[2],
                'active' => true,
                'sort_order' => array_search($index, ['farmacia', 'comercial', 'timbragem', 'lubrificantes']),
            ])->save();
        }

        $bannersData = [
            [
                'slug' => 'farmacia',
                'title' => 'Farmácia Gundja',
                'description' => 'Medicamentos essenciais, dermocosmética e acompanhamento farmacêutico dedicado em Luanda e Bailundo.',
                'image' => 'banners/banner-farmacia.png',
                'alt' => 'Banner Farmácia Gundja',
                'button_label' => 'Consultar produtos',
                'target_url' => '/farmacia/produtos',
            ],
            [
                'slug' => 'comercial',
                'title' => 'Gundja Comercial',
                'description' => 'Soluções para o seu negócio, comércio geral, produtos de escritório e tecnologia NEGOMIL ERP.',
                'image' => 'banners/banner-comercial.png',
                'alt' => 'Banner Gundja Comercial',
                'button_label' => 'Explorar produtos',
                'target_url' => '/comercial/produtos',
            ],
            [
                'slug' => 'timbragem',
                'title' => 'Gundja Timbragem',
                'description' => 'Personalização têxtil, brindes corporativos, tipografia e identidade visual para a sua empresa.',
                'image' => 'banners/banner-timbragem.png',
                'alt' => 'Banner Gundja Timbragem',
                'button_label' => 'Pedir orçamento',
                'target_url' => '/timbragem#contactar',
            ],
            [
                'slug' => 'lubrificantes',
                'title' => 'Gundja Lubrificantes',
                'description' => 'Óleos e lubrificantes automotivos e industriais de alta performance e máxima proteção.',
                'image' => 'banners/banner-lubrificantes.png',
                'alt' => 'Banner Gundja Lubrificantes',
                'button_label' => 'Ver lubrificantes',
                'target_url' => '/lubrificantes/produtos',
            ],
        ];

        foreach ($bannersData as $order => $bannerInfo) {
            $segment = Segment::where('slug', $bannerInfo['slug'])->first();
            if ($segment) {
                // Segment banner
                Banner::updateOrCreate(
                    ['segment_id' => $segment->id, 'placement' => 'segment'],
                    [
                        'title' => $bannerInfo['title'],
                        'description' => $bannerInfo['description'],
                        'image' => $bannerInfo['image'],
                        'alt' => $bannerInfo['alt'],
                        'button_label' => $bannerInfo['button_label'],
                        'target_url' => $bannerInfo['target_url'],
                        'active' => true,
                        'sort_order' => $order + 1,
                    ]
                );

                // Homepage banner
                Banner::updateOrCreate(
                    ['segment_id' => $segment->id, 'placement' => 'homepage'],
                    [
                        'title' => $bannerInfo['title'],
                        'description' => $bannerInfo['description'],
                        'image' => $bannerInfo['image'],
                        'alt' => $bannerInfo['alt'],
                        'button_label' => $bannerInfo['button_label'],
                        'target_url' => $bannerInfo['target_url'],
                        'active' => true,
                        'sort_order' => $order + 1,
                    ]
                );
            }
        }

        $pharmacy = Segment::where('slug', 'farmacia')->firstOrFail();
        foreach (['luanda' => ['Luanda', 'Luanda'], 'bailundo' => ['Bailundo', 'Huambo']] as $slug => $location) {
            if (! Branch::where('slug', 'farmacia-'.$slug)->exists()) {
                $branch = new Branch;
                $branch->forceFill(['name' => 'Farmácia Gundja — '.$location[0], 'slug' => 'farmacia-'.$slug, 'city' => $location[0], 'province' => $location[1], 'active' => true])->save();
                $branch->segments()->attach($pharmacy->id);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
