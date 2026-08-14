<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\DocumentVerificationLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(UserRole::SUPER_ADMIN, UserRole::ADMINISTRATOR, UserRole::AUDITOR), 403);
        $filters = $request->validate([
            'period' => ['nullable', 'in:today,7days,all'],
            'method' => ['nullable', 'in:QR_CAMERA,QR_LINK,MANUAL_CODE,PDF_HASH'],
            'result' => ['nullable', 'in:VALID,REVOKED,REPLACED,NOT_FOUND,NOT_ISSUED'],
        ]);
        $timezone = config('institution.timezone');
        $now = CarbonImmutable::now($timezone);
        $base = DocumentVerificationLog::query()
            ->when(! $request->user()->hasAnyRole(UserRole::SUPER_ADMIN), fn ($query) => $query->whereHas(
                'document', fn ($documents) => $documents->whereIn('clinic_id', $request->user()->accessibleClinicIds())
            ));
        $filtered = (clone $base)
            ->when(($filters['period'] ?? 'all') === 'today', fn ($query) => $query->where('verified_at', '>=', $now->startOfDay()->utc()))
            ->when(($filters['period'] ?? 'all') === '7days', fn ($query) => $query->where('verified_at', '>=', $now->subDays(6)->startOfDay()->utc()))
            ->when($filters['method'] ?? null, fn ($query, string $method) => $query->where('method', $method))
            ->when($filters['result'] ?? null, fn ($query, string $result) => $query->where('result', $result));
        $trendStart = $now->subDays(6)->startOfDay()->utc();
        $recent = (clone $base)->where('verified_at', '>=', $trendStart)
            ->selectRaw('DATE(verified_at) as day, COUNT(*) as aggregate')->groupByRaw('DATE(verified_at)')
            ->pluck('aggregate', 'day');
        $trend = collect(range(6, 0))->map(function (int $days) use ($recent, $now): array {
            $date = $now->subDays($days);

            return [
                'label' => $date->locale('es')->isoFormat('dd D'),
                'count' => (int) ($recent[$date->utc()->toDateString()] ?? 0),
            ];
        });
        $today = $now->startOfDay()->utc();
        $stats = (clone $base)->where('verified_at', '>=', $today)->selectRaw(
            'COUNT(*) as today,
            SUM(CASE WHEN method IN (?, ?) THEN 1 ELSE 0 END) as qr,
            SUM(CASE WHEN method = ? THEN 1 ELSE 0 END) as code,
            SUM(CASE WHEN method = ? THEN 1 ELSE 0 END) as pdf,
            SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) as valid,
            SUM(CASE WHEN result <> ? THEN 1 ELSE 0 END) as failed',
            ['QR_CAMERA', 'QR_LINK', 'MANUAL_CODE', 'PDF_HASH', 'VALID', 'VALID']
        )->first();
        $latest = (clone $base)->latest('verified_at')->first();

        return Inertia::render('Admin/Verifications/Index', [
            'logs' => (clone $filtered)->with(['document:id,patient_id,public_code,certificate_kind', 'document.patient:id,first_name,last_name'])
                ->latest('verified_at')->paginate(30)->withQueryString(),
            'stats' => [
                'today' => (int) $stats->today,
                'qr' => (int) $stats->qr,
                'code' => (int) $stats->code,
                'pdf' => (int) $stats->pdf,
                'valid' => (int) $stats->valid,
                'failed' => (int) $stats->failed,
                'latest' => ($latest?->verified_at ?? $latest?->created_at)?->timezone($timezone)->toIso8601String(),
            ],
            'trend' => $trend,
            'filters' => [
                'period' => $filters['period'] ?? 'all',
                'method' => $filters['method'] ?? '',
                'result' => $filters['result'] ?? '',
            ],
            'timezone' => $timezone,
        ]);
    }
}
