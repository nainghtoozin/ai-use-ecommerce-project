<?php

namespace App\Http\Controllers;

use ReflectionMethod;

abstract class Controller
{
    public function callAction($method, $parameters)
    {
        $rm = new ReflectionMethod($this, $method);
        $args = [];
        foreach ($rm->getParameters() as $i => $param) {
            $name = $param->getName();
            if (array_key_exists($name, $parameters)) {
                $args[] = $parameters[$name];
            } elseif (array_key_exists($i, $parameters)) {
                $args[] = $parameters[$i];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return $this->{$method}(...$args);
    }
}
