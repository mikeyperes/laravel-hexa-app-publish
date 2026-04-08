<?php

use hexa_app_publish\Support\PublishListCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lists')) {
            return;
        }

        if (!Schema::hasColumn('lists', 'description') || !Schema::hasColumn('lists', 'ai_prompt')) {
            return;
        }

        $now = now();

        foreach (PublishListCatalog::definitions() as $listKey => $definition) {
            foreach ($definition['items'] as $index => $item) {
                $existing = DB::table('lists')
                    ->where('list_key', $listKey)
                    ->where('list_value', $item['value'])
                    ->first();

                if (!$existing) {
                    DB::table('lists')->insert([
                        'list_key' => $listKey,
                        'list_value' => $item['value'],
                        'description' => $item['description'],
                        'ai_prompt' => $item['ai_prompt'],
                        'sort_order' => $index,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                $updates = [];

                if ($this->isBlank($existing->description ?? null)) {
                    $updates['description'] = $item['description'];
                }

                if ($this->isBlank($existing->ai_prompt ?? null)) {
                    $updates['ai_prompt'] = $item['ai_prompt'];
                }

                if (($existing->sort_order ?? null) === null) {
                    $updates['sort_order'] = $index;
                }

                if (($existing->is_active ?? null) === null) {
                    $updates['is_active'] = true;
                }

                if ($updates !== []) {
                    $updates['updated_at'] = $now;

                    DB::table('lists')
                        ->where('id', $existing->id)
                        ->update($updates);
                }
            }
        }
    }

    public function down(): void
    {
        // Seed/backfill migration. Keep user data intact on rollback.
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return trim($value) === '';
    }
};
