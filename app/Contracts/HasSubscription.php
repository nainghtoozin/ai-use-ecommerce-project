<?php

namespace App\Contracts;

use App\Models\Plan;

interface HasSubscription
{
    public function getActivePlan(): ?Plan;
}
