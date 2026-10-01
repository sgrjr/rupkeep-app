<?php

namespace App\Services;

use App\Models\UserLog;
use App\Models\Vehicle;
use App\Models\VehicleMaintenanceRecord;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The per-vehicle mileage and maintenance figures for a date range
 * (TASK-476).
 *
 * This used to live twice, once in MyReportsController and once in
 * AnnualVehicleReportModal, with the same query and the same arithmetic;
 * TASK-354, 408 and 422 each had to be fixed in both. Both now call this.
 *
 * Mileage per log comes from the UserLog accessors the invoice bills from,
 * so the report cannot disagree with the invoice:
 *
 *   total    = the odometer span (clock on to clock off), or the job span
 *              when the odometer was not recorded
 *   billable = UserLog::total_billable_miles
 *   deadhead = dead_head_driven, as recorded (never the whole span)
 *   release  = whatever is left: total - billable - deadhead
 *   personal = the gap between one log's clock-off reading and the next
 *              log's clock-on reading on the same vehicle
 *
 * Retired (soft-deleted) vehicles are included: a car sold in June still
 * drove its January-to-June miles and cost its oil changes.
 */
class AnnualVehicleReport
{
    /**
     * @return array<int, array{
     *     vehicle: Vehicle, total_miles: float, deadhead_miles: float, personal_miles: float,
     *     billable_miles: float, release_miles: float, logs_count: int,
     *     maintenance_count: int, maintenance_total_cost: float,
     *     maintenance_by_type: Collection, maintenance_records: Collection
     * }>
     */
    public static function build(int $organizationId, CarbonInterface $start, CarbonInterface $end, ?int $vehicleId = null): array
    {
        $vehicles = Vehicle::withTrashed()
            ->where('organization_id', $organizationId)
            ->when($vehicleId, fn ($q) => $q->where('id', $vehicleId))
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($vehicles as $vehicle) {
            $logs = UserLog::where('vehicle_id', $vehicle->id)
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($start, $end) {
                    $query->where(function ($q) use ($start, $end) {
                        $q->whereNotNull('started_at')->whereBetween('started_at', [$start, $end]);
                    })->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('started_at')->whereBetween('created_at', [$start, $end]);
                    });
                })
                ->orderByRaw('COALESCE(started_at, created_at)')
                ->get();

            $maintenance = VehicleMaintenanceRecord::where('vehicle_id', $vehicle->id)
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($start, $end) {
                    $query->where(function ($q) use ($start, $end) {
                        $q->whereNotNull('performed_at')->whereBetween('performed_at', [$start, $end]);
                    })->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('performed_at')->whereBetween('created_at', [$start, $end]);
                    });
                })
                ->orderBy('performed_at', 'desc')
                ->get();

            if ($logs->isEmpty() && $maintenance->isEmpty()) {
                continue;
            }

            $rows[] = array_merge(
                ['vehicle' => $vehicle, 'logs_count' => $logs->count()],
                self::mileage($logs),
                [
                    'maintenance_count' => $maintenance->count(),
                    'maintenance_total_cost' => (float) $maintenance->sum('cost'),
                    'maintenance_by_type' => $maintenance->groupBy('type')->map->count(),
                    'maintenance_records' => $maintenance,
                ]
            );
        }

        return $rows;
    }

    /**
     * @param  Collection<int, UserLog>  $logs  in driving order
     * @return array{total_miles: float, deadhead_miles: float, personal_miles: float, billable_miles: float, release_miles: float}
     */
    public static function mileage(Collection $logs): array
    {
        $total = $deadhead = $personal = $billable = $release = 0.0;
        $previous = null;

        foreach ($logs as $log) {
            $span = (float) $log->total_miles;

            if ($span <= 0 && $log->start_job_mileage !== null && $log->end_job_mileage !== null) {
                $span = max(0.0, (float) $log->end_job_mileage - (float) $log->start_job_mileage);
            }

            if ($span > 0) {
                $logBillable = (float) ($log->total_billable_miles ?? 0);
                $logDeadhead = (float) ($log->dead_head_driven ?? 0);

                $total += $span;
                $billable += $logBillable;
                $deadhead += $logDeadhead;
                $release += max(0.0, $span - $logBillable - $logDeadhead);

                if ($previous) {
                    $previousEnd = $previous->end_mileage ?? $previous->end_job_mileage;
                    $currentStart = $log->start_mileage ?? $log->start_job_mileage;

                    if ($previousEnd && $currentStart && $currentStart > $previousEnd) {
                        $personal += (float) $currentStart - (float) $previousEnd;
                    }
                }
            }

            $previous = $log;
        }

        return [
            'total_miles' => $total,
            'deadhead_miles' => $deadhead,
            'personal_miles' => $personal,
            'billable_miles' => $billable,
            'release_miles' => $release,
        ];
    }
}
