<?php

namespace App\Imports;

use App\Enums\UserType;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CustomerEventSeederImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $name = empty($row['name']) ? null : trim($row['name']);
        $email = empty($row['email']) ? null : trim($row['email']);
        $phone = empty($row['phone']) ? null : trim($row['phone']);
        if (!empty($phone)) {
            $phone = $phone[0] != '0' ? '0' . $phone : $phone;
        }
        $address = empty($row['address']) ? null : trim($row['address']);

        if (!empty($name) && !empty('phone') && !empty('email')) {
            $user = User::where('phone', $phone)->orWhere('email', $email)->first();
            if (!$user) {
                return new User([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'type' => UserType::CustomerEvent
                ]);
            }
        }
    }
}
