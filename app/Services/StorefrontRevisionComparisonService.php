<?php

namespace App\Services;

use App\Models\StorefrontRevision;

class StorefrontRevisionComparisonService
{
    public function compare(StorefrontRevision $from, StorefrontRevision $to): array
    {
        return [
            'from' => $this->revisionMeta($from),
            'to' => $this->revisionMeta($to),
            'changes' => array_values(array_filter([
                $this->compareIdentity($from->configuration ?? [], $to->configuration ?? []),
                $this->compareTheme($from->configuration ?? [], $to->configuration ?? []),
                $this->compareDesign($from->configuration ?? [], $to->configuration ?? []),
                $this->compareNavigation($from->configuration ?? [], $to->configuration ?? []),
                $this->compareHomepage($from->configuration ?? [], $to->configuration ?? []),
                $this->comparePromotions($from->configuration ?? [], $to->configuration ?? []),
                $this->compareMedia($from->configuration ?? [], $to->configuration ?? []),
                $this->compareLabels($from->configuration ?? [], $to->configuration ?? []),
            ])),
        ];
    }

    private function compareIdentity(array $from, array $to): ?array
    {
        return $this->scalarGroup('Store Identity', $from['identity'] ?? [], $to['identity'] ?? [], ['name' => 'Business/store name', 'site_title' => 'Website title', 'tagline' => 'Tagline', 'description' => 'Description']);
    }

    private function compareTheme(array $from, array $to): ?array
    {
        return $this->scalarGroup('Theme', $from['theme'] ?? [], $to['theme'] ?? [], ['name' => 'Theme', 'version' => 'Theme version']);
    }

    private function compareDesign(array $from, array $to): ?array
    {
        $changes = [];
        foreach (['color', 'radius', 'buttons', 'cards', 'typography'] as $group) {
            foreach (($to['design'][$group] ?? []) as $key => $value) {
                $previous = $from['design'][$group][$key] ?? null;
                if ($previous !== $value) {
                    $changes[] = ['label' => ucfirst($group) . ' ' . str_replace('_', ' ', $key), 'previous' => $previous ?? 'Not set', 'current' => $value ?? 'Not set'];
                }
            }
        }
        return $changes ? ['area' => 'Design Tokens', 'changes' => $changes] : null;
    }

    private function compareNavigation(array $from, array $to): ?array
    {
        $changes = [];
        $old = collect($from['navigation']['items'] ?? [])->keyBy('key');
        $new = collect($to['navigation']['items'] ?? [])->keyBy('key');
        foreach ($new as $key => $item) {
            if (!$old->has($key)) {
                $changes[] = ['label' => 'Navigation item added', 'previous' => 'Not present', 'current' => $item['label'] ?? $key];
                continue;
            }
            $previous = $old->get($key);
            foreach (['label' => 'Label', 'path' => 'Destination', 'position' => 'Position'] as $field => $label) {
                if (($previous[$field] ?? null) !== ($item[$field] ?? null)) $changes[] = ['label' => ($item['label'] ?? $key) . ' ' . $label, 'previous' => $previous[$field] ?? 'Not set', 'current' => $item[$field] ?? 'Not set'];
            }
        }
        foreach ($old as $key => $item) if (!$new->has($key)) $changes[] = ['label' => 'Navigation item removed', 'previous' => $item['label'] ?? $key, 'current' => 'Removed'];
        return $changes ? ['area' => 'Navigation', 'changes' => $changes] : null;
    }

    private function compareHomepage(array $from, array $to): ?array
    {
        $changes = [];
        $old = collect($from['homepage']['sections'] ?? [])->keyBy('type');
        $new = collect($to['homepage']['sections'] ?? [])->keyBy('type');
        foreach ($new as $type => $section) {
            $label = ucwords(str_replace('_', ' ', $type));
            $previous = $old->get($type);
            if (!$previous) { $changes[] = ['label' => 'Section added', 'previous' => 'Not present', 'current' => $label]; continue; }
            foreach (['enabled' => 'Enabled', 'variant' => 'Variant', 'position' => 'Position', 'desktop_visible' => 'Desktop visibility', 'mobile_visible' => 'Mobile visibility'] as $field => $name) {
                if (($previous[$field] ?? null) !== ($section[$field] ?? null)) $changes[] = ['label' => $label . ' ' . $name, 'previous' => $this->display($previous[$field] ?? null), 'current' => $this->display($section[$field] ?? null)];
            }
            foreach (['title' => 'Title', 'description' => 'Description', 'button_text' => 'Button'] as $field => $name) {
                if (($previous['configuration'][$field] ?? null) !== ($section['configuration'][$field] ?? null)) $changes[] = ['label' => $label . ' ' . $name, 'previous' => $previous['configuration'][$field] ?? 'Not set', 'current' => $section['configuration'][$field] ?? 'Not set'];
            }
        }
        foreach ($old as $type => $section) if (!$new->has($type)) $changes[] = ['label' => 'Section removed', 'previous' => ucwords(str_replace('_', ' ', $type)), 'current' => 'Removed'];
        return $changes ? ['area' => 'Homepage', 'changes' => $changes] : null;
    }

    private function comparePromotions(array $from, array $to): ?array
    {
        $changes = [];
        $old = collect($this->sectionData($from, 'promotion', 'promotions'))->keyBy('id');
        $new = collect($this->sectionData($to, 'promotion', 'promotions'))->keyBy('id');
        foreach ($new as $id => $promotion) {
            if (!$old->has($id)) { $changes[] = ['label' => 'Promotion added', 'previous' => 'Not present', 'current' => $promotion['title'] ?? 'Promotion']; continue; }
            $previous = $old->get($id);
            foreach (['title' => 'Title', 'cta_label' => 'CTA', 'image_url' => 'Image', 'is_active' => 'Visibility', 'starts_at' => 'Start date', 'ends_at' => 'End date'] as $field => $label) {
                if (($previous[$field] ?? null) !== ($promotion[$field] ?? null)) {
                    $previousValue = $field === 'image_url' ? ($previous[$field] ? 'Image assigned' : 'No image') : $this->display($previous[$field] ?? null);
                    $currentValue = $field === 'image_url' ? ($promotion[$field] ? 'Image assigned' : 'No image') : $this->display($promotion[$field] ?? null);
                    $changes[] = ['label' => ($promotion['title'] ?? 'Promotion') . ' ' . $label, 'previous' => $previousValue, 'current' => $currentValue];
                }
            }
        }
        foreach ($old as $id => $promotion) if (!$new->has($id)) $changes[] = ['label' => 'Promotion removed', 'previous' => $promotion['title'] ?? 'Promotion', 'current' => 'Removed'];
        return $changes ? ['area' => 'Promotions', 'changes' => $changes] : null;
    }

    private function compareMedia(array $from, array $to): ?array
    {
        $old = $this->mediaReferences($from);
        $new = $this->mediaReferences($to);
        $changes = [];
        foreach (array_diff($new, $old) as $id) $changes[] = ['label' => 'Media added', 'previous' => 'Not used', 'current' => 'New image'];
        foreach (array_diff($old, $new) as $id) $changes[] = ['label' => 'Media removed', 'previous' => 'Previous image', 'current' => 'Not used'];
        return $changes ? ['area' => 'Media', 'changes' => $changes] : null;
    }

    private function compareLabels(array $from, array $to): ?array
    {
        $changes = [];
        foreach (($to['content']['labels'] ?? []) as $key => $value) if (($from['content']['labels'][$key] ?? null) !== $value) $changes[] = ['label' => ucwords(str_replace('_', ' ', $key)), 'previous' => $from['content']['labels'][$key] ?? 'Default', 'current' => $value];
        return $changes ? ['area' => 'Content & Labels', 'changes' => $changes] : null;
    }

    private function scalarGroup(string $area, array $from, array $to, array $fields): ?array
    {
        $changes = [];
        foreach ($fields as $key => $label) if (($from[$key] ?? null) !== ($to[$key] ?? null)) $changes[] = ['label' => $label, 'previous' => $from[$key] ?? 'Not set', 'current' => $to[$key] ?? 'Not set'];
        return $changes ? ['area' => $area, 'changes' => $changes] : null;
    }

    private function sectionData(array $configuration, string $type, string $key): array
    {
        foreach ($configuration['homepage']['sections'] ?? [] as $section) if (($section['type'] ?? null) === $type) return $section['data'][$key] ?? [];
        return [];
    }

    private function mediaReferences(array $configuration): array
    {
        $references = [];
        foreach ($configuration['homepage']['sections'] ?? [] as $section) {
            $references = array_merge($references, $section['configuration']['media_ids'] ?? [], array_filter([$section['configuration']['media_id'] ?? null]));
            foreach ($section['data']['promotions'] ?? [] as $promotion) if (!empty($promotion['media_id'])) $references[] = $promotion['media_id'];
        }
        return array_values(array_unique(array_map('strval', $references)));
    }

    private function revisionMeta(StorefrontRevision $revision): array
    {
        return ['id' => $revision->id, 'revision_number' => $revision->revision_number, 'status' => $revision->status];
    }

    private function display(mixed $value): string
    {
        if ($value === null || $value === '') return 'Not set';
        if (is_bool($value)) return $value ? 'Enabled' : 'Disabled';
        return is_scalar($value) ? (string) $value : 'Changed';
    }
}
