<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Master\Sekolah;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'event' => 'nullable|string|max:50',
            'subject_type' => 'nullable|string|max:255',
            'q' => 'nullable|string|max:100',
            'sekolah_id' => 'nullable|uuid|exists:master_data_sekolah,id',
            'dari' => 'nullable|date',
            'sampai' => 'nullable|date',
        ]);

        $query = Activity::inLog('sistem')
            ->with(['causer', 'subject'])
            ->latest('id');

        if (! empty($validated['event'])) {
            $query->forEvent($validated['event']);
        }

        if (! empty($validated['subject_type'])) {
            $query->where('subject_type', $validated['subject_type']);
        }

        if (! empty($validated['sekolah_id'])) {
            $sekolahId = $validated['sekolah_id'];
            $query->where(function ($q) use ($sekolahId) {
                $q->where('properties->sekolah_id', $sekolahId)
                    ->orWhereHasMorph('causer', User::class, fn ($c) => $c->where('sekolah_id', $sekolahId));
            });
        }

        if (! empty($validated['q'])) {
            $keyword = trim($validated['q']);
            $query->where(function ($q) use ($keyword) {
                $q->where('description', 'like', "%{$keyword}%")
                    ->orWhere('properties->ringkasan', 'like', "%{$keyword}%");
            });
        }

        if (! empty($validated['dari'])) {
            $query->whereDate('created_at', '>=', $validated['dari']);
        }

        if (! empty($validated['sampai'])) {
            $query->whereDate('created_at', '<=', $validated['sampai']);
        }

        $events = Activity::inLog('sistem')
            ->whereNotNull('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->toArray();

        $subjectTypes = Activity::inLog('sistem')
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->toArray();

        return Inertia::render('Admin/ActivityLog/Index', [
            'items' => $query->paginate(50)->withQueryString(),
            'filters' => [
                'event' => $validated['event'] ?? '',
                'subject_type' => $validated['subject_type'] ?? '',
                'q' => $validated['q'] ?? '',
                'sekolah_id' => $validated['sekolah_id'] ?? '',
                'dari' => $validated['dari'] ?? '',
                'sampai' => $validated['sampai'] ?? '',
            ],
            'events' => $events,
            'subjectTypes' => $subjectTypes,
            'sekolahs' => Sekolah::orderBy('nama_sekolah')->select('id', 'nama_sekolah')->get(),
        ]);
    }
}
