<?php

namespace App\Contracts;

interface HasDatabaseTenantId
{
    public function databaseTenantId(): ?int;
}
