<?php

namespace App\Http\Controllers;

use ReflectionMethod;

abstract class Controller
{
    public function callAction($method, $parameters)
    {
        $rm = new ReflectionMethod($this, $method);

        // Build positional values from $parameters (post-dependency-resolution),
        // excluding the store_slug prefix route param.
        // This handles controllers whose parameter names differ from route param names
        // (e.g. OrderController::show(string $id) for route /{store_slug}/orders/{order})
        // while preserving container-resolved deps (e.g. Request) spliced at integer keys.
        $filtered = array_filter($parameters, fn ($key) => $key !== 'store_slug', ARRAY_FILTER_USE_KEY);

        $diValues = [];
        foreach ($filtered as $key => $value) {
            if (is_int($key)) {
                $diValues[] = $value;
            }
        }

        $positionalValues = array_values($filtered);

        $args = [];
        $diIndex = 0;
        foreach ($rm->getParameters() as $i => $param) {
            $name = $param->getName();

            if (array_key_exists($name, $parameters)) {
                $args[] = $parameters[$name];
            } elseif (isset($diValues[$diIndex])) {
                $args[] = $diValues[$diIndex];
                $diIndex++;
            } elseif (isset($positionalValues[$i])) {
                $args[] = $positionalValues[$i];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                $args[] = null;
            }
        }

        return $this->{$method}(...$args);
    }
}
