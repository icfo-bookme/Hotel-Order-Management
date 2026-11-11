<?php

namespace App\Services;

use App\Models\HotelRemark;
use App\Models\ShipTicketSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotelRemarkService
{
    public function store(array $data, int $userId): void
    {
        DB::transaction(function () use ($data, $userId) {
            $ticket = ShipTicketSale::lockForUpdate()->findOrFail($data['ticket_id']);
            if ($ticket->hotel_status !== 'pending') {
                throw ValidationException::withMessages(['ticket_id' => 'This ticket is no longer pending. Please refresh the list.']);
            }
            $remark = HotelRemark::firstOrNew(['ticket_id' => $ticket->id]);
            if (! $remark->exists) {
                $remark->added_by = $userId;
            }
            $remark->remark_text = $data['remark_text'];
            $remark->is_follow_up = (bool) ($data['is_follow_up'] ?? false);
            $remark->follow_up_date = $remark->is_follow_up ? $data['follow_up_date'] : null;
            $remark->save();
            $ticket->update(['hotel_status' => 'feedbacked']);
        });
    }

    public function update(array $data): void
    {
        $isFollowUp = (bool) ($data['is_follow_up'] ?? false);
        HotelRemark::where('ticket_id', $data['ticket_id'])->firstOrFail()->update([
            'remark_text' => $data['remark_text'],
            'is_follow_up' => $isFollowUp,
            'follow_up_date' => $isFollowUp ? $data['follow_up_date'] : null,
        ]);
    }
}
