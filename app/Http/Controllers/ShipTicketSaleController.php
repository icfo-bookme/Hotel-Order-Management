<?php

namespace App\Http\Controllers;

use App\Models\HotelRemark;
use Illuminate\Http\Request;
use App\Models\ShipTicketSale;
use App\Models\User;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class ShipTicketSaleController extends Controller
{

    public function index($status)
    {
        return view('ship_tickets.index', compact('status'));
    }

    // Server-side data
    public function getData(Request $request, $status)
    {
        $tickets = ShipTicketSale::with('remark.user')->select([
            'id',
            'customer_name',
            'customer_mobile',
            'whatsapp',
            'journey_date',
            'hotel_status'
        ])->where('hotel_status', $status);

        return DataTables::of($tickets)
            ->addIndexColumn()
            ->addColumn('feedback_by', function ($row) {
                // return user name if remark exists, otherwise '-'
                return $row->remark && $row->remark->user ? $row->remark->user->name : '-';
            })
            ->addColumn('remark_text', function ($row) {
                return $row->remark ? $row->remark->remark_text : '-';
            })
            ->addColumn('action', function ($row) {
                $buttons = '';

                if ($row->hotel_status == 'pending') {
                    $buttons .= '<button class="btn-remarks bg-blue-950 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Feedback</button>';
                } elseif ($row->hotel_status == 'Feedbacked') {
                    $buttons .= '<button class="btn-edit bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mr-2">Edit</button>';
                    // $buttons .= '<button class="btn-book bg-blue-950 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Book</button>';
                } elseif ($row->hotel_status == 'booked') {
                    $buttons .= '<span class="text-gray-500 font-semibold">Already Booked</span>';
                }

                return $buttons;
            })
            ->rawColumns(['action'])
            ->make(true);
    }


    public function store(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required',
            'remark_text' => 'required|string|max:1000',
        ]);

        HotelRemark::create([
            'ticket_id' => $request->input('ticket_id'),
            'remark_text' => $request->input('remark_text'),
            'added_by' => auth()->id(),
        ]);

        ShipTicketSale::where('id', $request->input('ticket_id'))
            ->update(['hotel_status' => 'Feedbacked']);


        return response()->json(['message' => 'Feedback saved successfully.']);
    }

    public function update(Request $request)
    {
        $request->validate([
            'ticket_id'   => 'required|exists:hotel_remarks,ticket_id',
            'remark_text' => 'required|string|max:1000',
        ]);

        HotelRemark::where('ticket_id', $request->ticket_id)
            ->update([
                'remark_text' => $request->remark_text,
            ]);

        return response()->json([
            'message' => 'Feedback updated successfully.'
        ]);
    }

   public function dashboard()
{
    $totalTickets = ShipTicketSale::count();
    $pendingTickets = ShipTicketSale::where('hotel_status', 'pending')->count();
    $feedbackedTickets = ShipTicketSale::where('hotel_status', 'Feedbacked')->count();
    $bookedTickets = ShipTicketSale::where('hotel_status', 'booked')->count();

    // Latest tickets
    $latestTickets = ShipTicketSale::with('remark.user')
                        ->orderBy('journey_date', 'desc')
                        ->take(5)
                        ->get();

    // Remarks grouped by user and date
    $remarksByDate = HotelRemark::with('user', 'ticket')
        ->select(
            'added_by',
            DB::raw('DATE(created_at) as remark_date'),
            DB::raw('COUNT(*) as total_remarks')
        )
        ->groupBy('added_by', DB::raw('DATE(created_at)'))
        ->orderBy('remark_date', 'desc')
        ->get();

    $lifetimeRemarks = HotelRemark::with('user')
    ->select('added_by', DB::raw('COUNT(*) as total_remarks'))
    ->groupBy('added_by')
    ->get();

    return view('dashboard', compact(
        'totalTickets',
        'pendingTickets',
        'feedbackedTickets',
        'bookedTickets',
        'latestTickets',
        'remarksByDate',
        'lifetimeRemarks'
    ));
} 
}
