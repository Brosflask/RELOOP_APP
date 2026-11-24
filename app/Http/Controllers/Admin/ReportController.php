<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): Response
    {
        [$from, $to] = $this->range($request);

        return Inertia::render('Admin/Reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $reports->summary($from, $to),
            'sales_by_day' => $reports->salesByDay($from, $to),
            'top_sellers' => $reports->topSellers($from, $to),
            'materials_sold' => $reports->materialsSold($from, $to),
            'impact' => $reports->impact($from, $to),
        ]);
    }

    /** CSV of payments in the range, for accounting and reconciliation with MTN statements. */
    public function exportTransactions(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $filename = sprintf('reloop-transactions-%s-to-%s.csv', $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['payment_id', 'created_at', 'verified_at', 'order_code', 'payer', 'payee', 'provider', 'amount', 'currency', 'status', 'transaction_reference', 'provider_reference', 'requires_attention', 'failure_reason']);

            Payment::query()
                ->with(['order:id,code', 'payer:id,name', 'payee:id,name'])
                ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                ->orderBy('id')
                ->chunk(500, function ($payments) use ($out) {
                    foreach ($payments as $p) {
                        fputcsv($out, [
                            $p->id, $p->created_at?->toIso8601String(), $p->verified_at?->toIso8601String(),
                            $p->order?->code, $p->payer?->name, $p->payee?->name, $p->provider->value, (string) $p->amount, $p->currency,
                            $p->status->value, $p->transaction_reference, $p->provider_reference,
                            $p->requires_attention ? 'yes' : 'no', $p->failure_reason,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : CarbonImmutable::today();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : $to->subDays(29);

        return [$from, $to];
    }
}
