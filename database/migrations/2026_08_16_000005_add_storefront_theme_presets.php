<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $presets = [
            [
                'slug' => 'commerce-default',
                'name' => 'Modern Commerce',
                'version' => '2.0.0',
                'tokens' => [
                    'color' => ['primary' => '#2563EB', 'secondary' => '#1D4ED8', 'accent' => '#F59E0B', 'background' => '#F8FAFC', 'surface' => '#FFFFFF', 'surface_muted' => '#F1F5F9', 'text' => '#0F172A', 'text_muted' => '#64748B', 'border' => '#E2E8F0', 'success' => '#16A34A', 'warning' => '#D97706', 'danger' => '#DC2626'],
                    'typography' => ['font_family' => 'Figtree', 'heading_weight' => '700', 'body_weight' => '400', 'heading_scale' => '1.15', 'body_size' => '1rem', 'small_size' => '0.875rem', 'line_height' => '1.5'],
                    'layout' => ['page_width' => '80rem', 'section_spacing' => '2rem', 'content_spacing' => '1rem', 'grid_gap' => '1rem'],
                    'radius' => ['button' => '0.5rem', 'card' => '0.75rem', 'input' => '0.5rem', 'border_width' => '1px'],
                    'elevation' => ['card' => '0 1px 3px 0 rgb(15 23 42 / 0.10)', 'dropdown' => '0 10px 25px -5px rgb(15 23 42 / 0.15)', 'modal' => '0 20px 40px -10px rgb(15 23 42 / 0.25)'],
                    'buttons' => ['primary_style' => 'solid'], 'cards' => ['style' => 'bordered'], 'inputs' => ['style' => 'outlined'], 'product_cards' => ['variant' => 'standard'], 'variants' => ['hero' => 'split', 'categories' => 'grid', 'products' => 'grid', 'brand_story' => 'split', 'cta' => 'centered'],
                ],
            ],
            [
                'slug' => 'minimal-store',
                'name' => 'Minimal Store',
                'version' => '1.0.0',
                'tokens' => [
                    'color' => ['primary' => '#111827', 'secondary' => '#374151', 'accent' => '#6B7280', 'background' => '#FFFFFF', 'surface' => '#FFFFFF', 'surface_muted' => '#F9FAFB', 'text' => '#111827', 'text_muted' => '#6B7280', 'border' => '#E5E7EB', 'success' => '#15803D', 'warning' => '#A16207', 'danger' => '#B91C1C'],
                    'typography' => ['font_family' => 'Figtree', 'heading_weight' => '600', 'body_weight' => '400', 'heading_scale' => '1.08', 'body_size' => '1rem', 'small_size' => '0.875rem', 'line_height' => '1.6'],
                    'layout' => ['page_width' => '72rem', 'section_spacing' => '1.5rem', 'content_spacing' => '0.75rem', 'grid_gap' => '0.75rem'],
                    'radius' => ['button' => '0.25rem', 'card' => '0.25rem', 'input' => '0.25rem', 'border_width' => '1px'],
                    'elevation' => ['card' => 'none', 'dropdown' => '0 5px 12px -4px rgb(17 24 39 / 0.12)', 'modal' => '0 15px 30px -8px rgb(17 24 39 / 0.18)'],
                    'buttons' => ['primary_style' => 'outline'], 'cards' => ['style' => 'flat'], 'inputs' => ['style' => 'outlined'], 'product_cards' => ['variant' => 'compact'], 'variants' => ['hero' => 'centered', 'categories' => 'compact', 'products' => 'compact', 'brand_story' => 'text-only', 'cta' => 'centered'],
                ],
            ],
            [
                'slug' => 'elegant-fashion',
                'name' => 'Elegant Fashion',
                'version' => '1.0.0',
                'tokens' => [
                    'color' => ['primary' => '#9D174D', 'secondary' => '#BE185D', 'accent' => '#B45309', 'background' => '#FFF7FB', 'surface' => '#FFFFFF', 'surface_muted' => '#FDF2F8', 'text' => '#3F1727', 'text_muted' => '#85606E', 'border' => '#F5D0E0', 'success' => '#15803D', 'warning' => '#B45309', 'danger' => '#BE123C'],
                    'typography' => ['font_family' => 'Figtree', 'heading_weight' => '700', 'body_weight' => '400', 'heading_scale' => '1.18', 'body_size' => '1rem', 'small_size' => '0.875rem', 'line_height' => '1.55'],
                    'layout' => ['page_width' => '78rem', 'section_spacing' => '2.5rem', 'content_spacing' => '1.25rem', 'grid_gap' => '1.25rem'],
                    'radius' => ['button' => '9999px', 'card' => '1rem', 'input' => '0.75rem', 'border_width' => '1px'],
                    'elevation' => ['card' => '0 8px 20px -12px rgb(157 23 77 / 0.35)', 'dropdown' => '0 12px 28px -8px rgb(63 23 39 / 0.20)', 'modal' => '0 22px 45px -12px rgb(63 23 39 / 0.28)'],
                    'buttons' => ['primary_style' => 'solid'], 'cards' => ['style' => 'raised'], 'inputs' => ['style' => 'outlined'], 'product_cards' => ['variant' => 'image-focused'], 'variants' => ['hero' => 'split', 'categories' => 'horizontal', 'products' => 'image-focused', 'brand_story' => 'split', 'cta' => 'full-width'],
                ],
            ],
        ];

        foreach ($presets as $preset) {
            DB::table('themes')->updateOrInsert(
                ['slug' => $preset['slug']],
                ['name' => $preset['name'], 'version' => $preset['version'], 'default_tokens' => json_encode($preset['tokens'], JSON_THROW_ON_ERROR), 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('themes')->whereIn('slug', ['minimal-store', 'elegant-fashion'])->delete();
    }
};
