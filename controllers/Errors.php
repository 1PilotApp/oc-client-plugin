<?php

namespace OnePilot\Client\Controllers;

use Carbon\Carbon;
use System\Models\EventLog;

class Errors
{
    /** @var int */
    const PAGINATION = 20;

    /** @var array intervals in minutes */
    const INTERVALS = [
        1 * 24 * 60,
        7 * 24 * 60,
        30 * 24 * 60,
    ];

    public function index()
    {
        $from = input('from') ? Carbon::parse(input('from'), 'UTC') : null;
        $to = input('to') ? Carbon::parse(input('to'), 'UTC') : null;
        $levels = is_array($levels = input('levels')) ? $levels : null;
        $search = input('search');

        return EventLog::select('id', 'level', 'message', 'created_at as date')
            ->orderBy('created_at', 'desc')
            ->when($levels, function ($query) use ($levels) {
                return $query->whereIn('level', $levels);
            })
            ->when($from, function ($query) use ($from) {
                return $query->where('created_at', '>=', $from);
            })
            ->when($to, function ($query) use ($to) {
                return $query->where('created_at', '<=', $to);
            })
            ->when($search, function ($query) use ($search) {
                return $query->where('message', 'LIKE', "%{$search}%");
            })
            ->paginate(input('paginate', self::PAGINATION));
    }

    /**
     * Return the log activity of the last day,week,month by Level
     * @return array
     */
    public function overview()
    {
        return collect(self::INTERVALS)->mapWithKeys(function ($interval) {
            return [$interval => $this->last($interval)];
        });
    }

    private function last($minutes)
    {
        $fromDate = Carbon::now()->subMinutes($minutes);

        return EventLog::select('level')
            ->selectRaw('count(*) as total')
            ->where('created_at', '>', $fromDate)
            ->groupBy('level')
            ->get();
    }
}
