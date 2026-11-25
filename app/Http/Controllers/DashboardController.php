<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\HealthStation;
use App\Models\ImmunizationRecord;
use App\Models\SmsNotification;
use App\Models\User;
use App\Models\VaccinationSchedule;
use App\Models\VaccineInventory;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function redirect()
    {
        return redirect()->route(Auth::user()->isCho() ? 'cho.dashboard' : 'bhw.dashboard');
    }

    public function index()
    {
        $user = Auth::user();
        $childrenQuery = Child::query();
        $scheduleQuery = VaccinationSchedule::query();
        $recordQuery = ImmunizationRecord::query();

        if ($user->isBhw()) {
            $stationId = $user->health_station_id ?: -1;
            $childrenQuery->where('health_station_id', $stationId);
            $scheduleQuery->whereHas('child', fn ($children) => $children->where('health_station_id', $stationId));
            $recordQuery->whereHas('child', fn ($children) => $children->where('health_station_id', $stationId));
        }

        $schedules = (clone $scheduleQuery)->with(['child', 'vaccineDefinition'])->get();
        $pendingSchedules = $schedules->filter(fn ($schedule) => $schedule->display_status === 'PENDING');
        $dueSchedules = $schedules->filter(fn ($schedule) => $schedule->display_status === 'DUE');
        $overdueSchedules = $schedules->filter(fn ($schedule) => $schedule->display_status === 'OVERDUE');
        $invalidSchedules = $schedules->filter(fn ($schedule) => $schedule->display_status === 'INVALID');
        $stats = [
            'total_registered' => (clone $childrenQuery)->count(),
            'upcoming' => $pendingSchedules->count(),
            'delayed' => $dueSchedules->count(),
            'missed' => $overdueSchedules->count(),
            'invalid' => $invalidSchedules->count(),
        ];

        $upcomingVaccinations = $pendingSchedules->sortBy('due_date')->take(6)->values();
        $delayedVaccinations = $dueSchedules->sortBy('due_date')->take(6)->values();
        $missedVaccinations = $overdueSchedules->sortBy('due_date')->take(6)->values();
        $recentRecords = (clone $recordQuery)->with('child')->latest('administered_at')->limit(8)->get();
        $notifications = [
            'delayed' => $stats['delayed'],
            'missed' => $stats['missed'],
            'reminders' => SmsNotification::query()
                ->when($user->isBhw(), fn ($query) => $query->whereHas('child', fn ($children) => $children->where('health_station_id', $user->health_station_id ?: -1)))
                ->where('status', 'PENDING')->count(),
        ];

        return view('dashboard.index', compact(
            'stats',
            'upcomingVaccinations',
            'delayedVaccinations',
            'missedVaccinations',
            'recentRecords',
            'notifications'
        ));
    }

    public function choDashboard()
    {
        $children = Child::count();
        $coveredChildren = Child::whereHas('immunizationRecords')->count();
        $coverage = $children ? round($coveredChildren / $children * 100, 1) : 0;
        $schedules = VaccinationSchedule::with('vaccineDefinition')->get();
        $scheduleCounts = $schedules->countBy(fn ($schedule) => $schedule->display_status);
        $stationBarangays = HealthStation::with('barangays')->get();
        $barangayCount = $stationBarangays->flatMap(fn ($station) => $station->barangays->isNotEmpty()
            ? $station->barangays->pluck('official_name')
            : collect([$station->barangay]))->filter()->unique()->count();
        $stats = [
            'children' => $children,
            'stations' => HealthStation::count(),
            'barangays' => $barangayCount,
            'bhws' => User::where('role', 'BHW')->where('account_status', 'ACTIVE')->count(),
            'coverage' => $coverage,
            'upcoming' => $scheduleCounts->get('PENDING', 0),
            'delayed' => $scheduleCounts->get('DUE', 0),
            'missed' => $scheduleCounts->get('OVERDUE', 0),
            'invalid' => $scheduleCounts->get('INVALID', 0),
            'low_stock' => VaccineInventory::whereColumn('quantity', '<=', 'reorder_level')->where('quantity', '>', 0)->count(),
        ];
        $lowStockStations = HealthStation::with('barangays')->withCount(['vaccineInventory as low_stock_count' => fn ($query) => $query->whereColumn('quantity', '<=', 'reorder_level')->where('quantity', '>', 0)])
            ->orderByDesc('low_stock_count')->get()->filter(fn ($station) => $station->low_stock_count > 0)->take(5);
        $notifications = [
            'delayed' => $stats['delayed'],
            'missed' => $stats['missed'],
            'low_stock' => $stats['low_stock'],
        ];

        return view('cho.dashboard', compact('stats', 'lowStockStations', 'notifications'));
    }
}
