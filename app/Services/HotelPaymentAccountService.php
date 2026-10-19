<?php

namespace App\Services;

use App\Models\HotelPaymentAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class HotelPaymentAccountService
{
    public function __construct(private readonly BookmeService $bookme)
    {
    }

    public function table()
    {
        return DataTables::eloquent(HotelPaymentAccount::query())->escapeColumns([])->toJson();
    }

    public function create(array $data, int $userId): HotelPaymentAccount
    {
        $hotel = collect($this->bookme->all())->firstWhere('hotel_id', (int) $data['bookme_hotel_id']);
        if (!$hotel) {
            throw ValidationException::withMessages(['bookme_hotel_id' => 'Select a valid Bookme hotel.']);
        }
        $data['hotel_name'] = $hotel['text'];
        $data['created_by'] = $userId;

        return $this->save($data);
    }

    public function update(HotelPaymentAccount $account, array $data): HotelPaymentAccount
    {
        $data['bookme_hotel_id'] = $account->bookme_hotel_id;

        return $this->save($data, $account);
    }

    private function save(array $data, ?HotelPaymentAccount $account = null): HotelPaymentAccount
    {
        if ($data['is_default'] && !$data['is_active']) {
            throw ValidationException::withMessages(['is_default' => 'A default account must be active.']);
        }
        if ($data['payment_method'] !== 'bank') {
            $data['bank_name'] = $data['branch_name'] = $data['routing_number'] = null;
        }

        return Cache::lock('hotel-payment-accounts.'.$data['bookme_hotel_id'], 15)->block(5, function () use ($data, $account) {
            return DB::transaction(function () use ($data, $account) {
                if ($data['is_default']) {
                    HotelPaymentAccount::where('bookme_hotel_id', $data['bookme_hotel_id'])->update(['is_default' => false]);
                }
                $account ??= new HotelPaymentAccount();
                if ($account->exists) {
                    $account->refresh();
                }
                $account->fill($data)->save();

                return $account->refresh();
            });
        });
    }
}
