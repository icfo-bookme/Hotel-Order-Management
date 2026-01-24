<x-app-layout>




    <div class="">
        @include('layouts.tab')
    </div>
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-6">Ship Tickets Dashboard</h1>

    <!-- ================== Stats Cards ================== -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-blue-500 text-white p-4 rounded shadow">
            <h2 class="text-lg font-semibold">Total Tickets</h2>
            <p class="text-3xl">{{ $totalTickets }}</p>
        </div>
        <div class="bg-yellow-500 text-white p-4 rounded shadow">
            <h2 class="text-lg font-semibold">Pending</h2>
            <p class="text-3xl">{{ $pendingTickets }}</p>
        </div>
        <div class="bg-green-500 text-white p-4 rounded shadow">
            <h2 class="text-lg font-semibold">Feedbacked</h2>
            <p class="text-3xl">{{ $feedbackedTickets }}</p>
        </div>
        <div class="bg-gray-500 text-white p-4 rounded shadow">
            <h2 class="text-lg font-semibold">Booked</h2>
            <p class="text-3xl">{{ $bookedTickets }}</p>
        </div>
    </div>

    <!-- ================== Latest Tickets Table ================== -->
    <h2 class="text-xl font-bold mb-2">Latest Tickets</h2>
    <div class="overflow-x-auto mb-8">
        <table class="min-w-full bg-white shadow rounded">
            <thead>
                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">Customer</th>
                    <th class="py-3 px-6 text-left">Mobile</th>
                    <th class="py-3 px-6 text-left">Journey Date</th>
                    <th class="py-3 px-6 text-left">Status</th>
                    <th class="py-3 px-6 text-left">Feedback By</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                @foreach($latestTickets as $ticket)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="py-3 px-6 text-left">{{ $ticket->customer_name }}</td>
                    <td class="py-3 px-6 text-left">{{ $ticket->customer_mobile }}</td>
                    <td class="py-3 px-6 text-left">{{ $ticket->journey_date }}</td>
                    <td class="py-3 px-6 text-left">{{ $ticket->hotel_status }}</td>
                    <td class="py-3 px-6 text-left">
                        {{ $ticket->remark && $ticket->remark->user ? $ticket->remark->user->name : '-' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- ================== Remarks by User & Date Table ================== -->
    <h2 class="text-xl font-bold mb-2">Remarks by User & Date</h2>
    <div class="overflow-x-auto mb-8">
    <table class="min-w-full bg-white shadow rounded">
        <thead>
            <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                <th class="py-3 px-6 text-left">User</th>
                <th class="py-3 px-6 text-left">Total Remarks</th>
            </tr>
        </thead>
        <tbody class="text-gray-600 text-sm font-light">
            @foreach($lifetimeRemarks as $remark)
            <tr class="border-b border-gray-200 hover:bg-gray-100">
                <td class="py-3 px-6 text-left">{{ $remark->user->name }}</td>
                <td class="py-3 px-6 text-left">{{ $remark->total_remarks }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>


    <!-- ================== Interactive Chart ================== -->
    <h2 class="text-xl font-bold mb-2">Remarks Chart (Hover to See User & Count)</h2>
    <canvas id="remarksChart" class="mb-8"></canvas>
</div>

<!-- ================== Chart JS ================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('remarksChart').getContext('2d');

// Prepare unique dates for X-axis
const dates = [...new Set(@json($remarksByDate->pluck('remark_date')))];

// Prepare datasets for all users
const users = [...new Set(@json($remarksByDate->pluck('user.name')))];
const rawData = @json($remarksByDate);

const datasets = users.map(user => {
    return {
        label: user,
        data: dates.map(date => {
            const item = rawData.find(r => r.user.name === user && r.remark_date === date);
            return item ? item.total_remarks : 0;
        }),
        fill: false,
        borderColor: '#'+Math.floor(Math.random()*16777215).toString(16),
        tension: 0.1,
        pointHoverRadius: 7,
        pointHoverBackgroundColor: 'red',
        pointHoverBorderColor: 'black'
    };
});

new Chart(ctx, {
    type: 'line',
    data: {
        labels: dates,
        datasets: datasets
    },
    options: {
        responsive: true,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const user = context.dataset.label;
                        const date = context.label;
                        const count = context.parsed.y;
                        return `${user} on ${date}: ${count} remark(s)`;
                    }
                }
            },
            legend: { position: 'bottom' }
        },
        scales: {
            y: { beginAtZero: true, precision:0 }
        }
    }
});
</script>
</x-app-layout>
