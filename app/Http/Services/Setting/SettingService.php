<?php

namespace App\Http\Services\Setting;

use App\Enums\SettingEnum;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Setting\SettingRepositoryInterface;
use App\Interfaces\Services\Setting\SettingServiceInterface;
use Illuminate\Support\Collection;

class SettingService extends BaseService implements SettingServiceInterface
{
    public function __construct(protected SettingRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function bankTransferInfo(): string
    {
        $bankName = $this->repository->valueOf(SettingEnum::BANK_NAME->value) ?? 'BCA';
        $bankHolder = $this->repository->valueOf(SettingEnum::BANK_HOLDER->value) ?? 'PT. Platinum Adi Sentosa';
        $bankAccount = $this->repository->valueOf(SettingEnum::BANK_ACCOUNT->value) ?? '2883123808';

        return $bankName.' An. '.$bankHolder.'<br>'.$bankAccount;
    }
}
