<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\Category;
use App\Models\SessionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class SessionLogController extends Controller
{
    public const SESSION_KEY = 'annotation_session_id';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $sessionLogs = SessionLog::with('package:id,name')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('_app.app', [
            'content' => 'annotation.session-logs.index',
            'headerdata' => ['pagetitle' => 'Annotation Sessions'],
            'sidenavdata' => ['active' => 'annotations'],
            'sessionLogs' => $sessionLogs,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort(404);
    }

    /**
     * Display the specified resource.
     */
    public function show(SessionLog $sessionLog)
    {
        $user = Auth::user();
        abort_unless($user && $sessionLog->user_id === $user->id, 403);

        $sessionLog->load('package:id,name');

        $annotationOrder = collect($sessionLog->annotation_datas ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $annotations = Annotation::with('data')
            ->whereIn('id', $annotationOrder)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('id');

        $reviewItems = $annotationOrder
            ->map(function (int $annotationId) use ($annotations) {
                $annotation = $annotations->get($annotationId);

                if (! $annotation || ! $annotation->data) {
                    return null;
                }

                return [
                    'annotation_id' => $annotation->id,
                    'data_id' => (string) $annotation->data_id,
                    'content' => $annotation->data->content,
                    'category_ids' => array_map('strval', $annotation->category_ids ?? []),
                ];
            })
            ->filter()
            ->values();

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('_app.app', [
            'content' => 'annotation.annotate',
            'headerdata' => ['pagetitle' => 'Review Session'],
            'sidenavdata' => ['active' => 'annotations'],
            'workbenchPackage' => $sessionLog->package,
            'categories' => $categories,
            'initialWorkItem' => $reviewItems->first(),
            'reviewItems' => $reviewItems,
            'isSessionReview' => true,
            'sessionLog' => $sessionLog,
        ]);
    }

    public function end(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $sessionLogId = session(self::SESSION_KEY);

        if (! $sessionLogId) {
            return response()->json([
                'message' => 'No active session to end.',
            ], 422);
        }

        $sessionLog = SessionLog::where('id', $sessionLogId)
            ->where('user_id', $user->id)
            ->first();

        if (! $sessionLog) {
            session()->forget(self::SESSION_KEY);

            return response()->json([
                'message' => 'Session already closed.',
            ]);
        }

        if (! $sessionLog->ended_at) {
            $sessionLog->ended_at = Carbon::now();
            $sessionLog->save();
        }

        session()->forget(self::SESSION_KEY);

        return response()->json([
            'message' => 'Annotation session ended.',
        ]);
    }

    public function history()
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $logs = SessionLog::with('package:id,name')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(function (SessionLog $log) {
                return [
                    'id' => $log->id,
                    'package' => $log->package?->name,
                    'annotation_count' => count($log->annotation_datas ?? []),
                    'started_at' => optional($log->created_at)->toDateTimeString(),
                    'ended_at' => optional($log->ended_at)->toDateTimeString(),
                    'is_active' => $log->ended_at === null,
                ];
            });

        return response()->json([
            'data' => $logs,
        ]);
    }
}
