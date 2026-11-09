<?php

declare(strict_types=1);

namespace Apachish\WpRepo\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
/**
 * Seeds the panel permissions for every configured guard, registers the
 * package row in the host app's `packages` table and links its permissions
 * through `package_permission`. Safe to run repeatedly.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $panel = Permission::query()->where('name', 'panel')->first();

        if ($panel === null) {
            $this->command?->warn('WpRepo: permission "panel" not found, seeding skipped. Seed it first and re-run.');

            return;
        }

        $permissionIds = [];

        foreach ((array) config('wp-repo.guards', ['web', 'project']) as $guard) {
            foreach ($this->tree() as $name => $definition) {
                $parent = Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => $guard],
                    ['title' => $definition['title'], 'parent_id' => $panel->id],
                );
                $permissionIds[] = $parent->id;

                foreach ($definition['children'] as $childName => $childTitle) {
                    $child = Permission::updateOrCreate(
                        ['name' => $childName, 'guard_name' => $guard],
                        ['title' => $childTitle, 'parent_id' => $parent->id],
                    );
                    $permissionIds[] = $child->id;
                }
            }
        }

        $this->registerPackage($permissionIds);
    }

    /**
     * @return array<string, array{title: string, children: array<string, string>}>
     */
    private function tree(): array
    {
        return [
            'manage-wp-repo' => [
                'title' => 'wp-repo::messages.permissions.manage',
                'children' => [],
            ],

        ];
    }

    /**
     * @param  array<int, int|string>  $permissionIds
     */
    private function registerPackage(array $permissionIds): void
    {
        $packageClass = config('notification.models.package');

        if (! is_string($packageClass) || ! class_exists($packageClass)) {
            return;
        }

        $package = (array) config('notification.package');

        $attributes = array_filter([
            'name' => $package['name'] ?? null,
            'icon' => $package['icon'] ?? null,
            'version' => $package['version'] ?? null,
            'description' => $package['description'] ?? null,
            'status' => $package['status'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        $record = $packageClass::updateOrCreate(['slug' => $package['slug']], $attributes);

        if (! Schema::hasTable('package_permission')) {
            return;
        }

        foreach (array_unique($permissionIds) as $permissionId) {
            DB::table('package_permission')->updateOrInsert([
                'package_id' => $record->getKey(),
                'permission_id' => $permissionId,
            ]);
        }
    }
}
