<?php

namespace App\Http\Repositories\UserDiscount;

use App\Http\Repositories\BaseRepository;
use App\Interfaces\Repositories\UserDiscount\UserDiscountRepositoryInterface;
use App\Models\UserDiscount;

class UserDiscountRepository extends BaseRepository implements UserDiscountRepositoryInterface
{
    public function __construct(UserDiscount $model)
    {
        parent::__construct($model);
    }
}
