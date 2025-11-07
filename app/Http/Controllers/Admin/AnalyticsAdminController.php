<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsAdminController extends Controller
{
    private function buildSeries($model, string $dateColumn, string $valueColumn, string $statusColumn = null, array $statusIn = [], int $days = 90, string $interval = 'day')
    {
        $from = now()->subDays($days)->startOfDay();

        $query = $model::query()
            ->selectRaw(($interval === 'week' ? "DATE_FORMAT($dateColumn, '%x-%v')" : "DATE($dateColumn)") . ' as bucket')
            ->selectRaw("SUM($valueColumn) as total")
            ->where($dateColumn, '>=', $from);

        if ($statusColumn && $statusIn) {
            $query->whereIn($statusColumn, $statusIn);
        }

        $query->groupBy('bucket')->orderBy('bucket');
        $rows = $query->get();

        $series = [];
        $labels = [];

        if ($interval === 'week') {
            $period = new \DatePeriod($from->copy()->startOfWeek(), new \DateInterval('P1W'), now()->endOfWeek());
            foreach ($period as $p) {
                $bucket = $p->format('o-W');
                $labels[] = $bucket;
                $match = $rows->firstWhere('bucket', $bucket);
                $series[] = (float) ($match->total ?? 0);
            }
        } else {
            $period = new \DatePeriod($from, new \DateInterval('P1D'), now()->endOfDay());
            foreach ($period as $p) {
                $bucket = $p->format('Y-m-d');
                $labels[] = $bucket;
                $match = $rows->firstWhere('bucket', $bucket);
                $series[] = (float) ($match->total ?? 0);
            }
        }

        return [$labels, $series];
    }

    public function donations(Request $request)
    {
        $request->validate([
            'days' => 'nullable|integer|min:7|max:365',
            'interval' => 'nullable|in:day,week',
        ]);
        $days = (int) $request->input('days', 90);
        $interval = $request->input('interval', 'day');

        [$labels, $donSeries] = $this->buildSeries(Donation::class, 'created_at', 'amount', 'status', ['succeeded'], $days, $interval);

        return response()->json([
            'status' => 'success',
            'data' => [
                'labels' => $labels,
                'series' => [
                    'donations' => $donSeries,
                ],
            ],
        ]);
    }

    public function withdrawals(Request $request)
    {
        $request->validate([
            'days' => 'nullable|integer|min:7|max:365',
            'interval' => 'nullable|in:day,week',
        ]);
        $days = (int) $request->input('days', 90);
        $interval = $request->input('interval', 'day');

        [$labels, $wdSeries] = $this->buildSeries(Withdrawal::class, 'created_at', 'amount', 'status', ['approved'], $days, $interval);

        return response()->json([
            'status' => 'success',
            'data' => [
                'labels' => $labels,
                'series' => [
                    'withdrawals' => $wdSeries,
                ],
            ],
        ]);
    }
}
