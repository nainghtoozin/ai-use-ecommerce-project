<?php

namespace App\Contracts;

interface Identity
{
    public function getId(): mixed;

    public function getEmail(): string;

    public function getStatusString(): string;

    public function getProfileImageUrl(): ?string;
}
