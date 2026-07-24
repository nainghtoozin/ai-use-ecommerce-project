<?php

namespace App\Inertia;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Inertia\ProvidesInertiaProperties;
use Inertia\Response;
use Inertia\ResponseFactory;
use InvalidArgumentException;
use UnitEnum;

class InertiaResponseFactory extends ResponseFactory
{
    public function render($component, $props = []): Response
    {
        $component = $this->transformComponent($component);

        $component = match (true) {
            $component instanceof BackedEnum => $component->value,
            $component instanceof UnitEnum => $component->name,
            default => $component,
        };

        if (! is_string($component)) {
            throw new InvalidArgumentException('Component argument must be of type string or a string BackedEnum');
        }

        if (config('inertia.pages.ensure_pages_exist', false)) {
            $this->findComponentOrFail($component);
        }

        if ($props instanceof Arrayable) {
            $props = $props->toArray();
        } elseif ($props instanceof ProvidesInertiaProperties) {
            $props = [$props];
        }

        return new InertiaResponse(
            $component,
            $this->sharedProps,
            $props,
            $this->rootView,
            $this->getVersion(),
            $this->encryptHistory ?? config('inertia.history.encrypt', false),
            $this->urlResolver,
        );
    }
}
