<?php

namespace App\Interfaces\Services\Product;

use App\Interfaces\Services\BaseServiceInterface;

interface ProductServiceInterface extends BaseServiceInterface
{
    public function import(mixed $file): void;
}
