<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ReportExport;
use App\Models\Segment;
use App\Services\Access;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, Access $access, ReportService $reports): Response
    {
        $user = $request->user();
        $types = array_values(array_filter(ReportService::TYPES, fn ($type) => $access->any($user, 'reports.'.$type)));
        abort_if($types === [], 403);
        $request->merge(['type' => $request->input('type', $types[0])]);
        $filters = $reports->filters($request);
        $records = $reports->query($user, $filters)->paginate(30)->withQueryString();
        $records->getCollection()->transform(fn ($row) => $reports->row($user, $row, $filters));

        return Inertia::render('Admin/Reports', ['records' => $records, 'filters' => $filters, 'types' => $types,
            'segments' => $access->scope($user, Segment::query(), 'segments.view')->get(['id', 'name']),
            'branches' => $access->scope($user, Branch::query(), 'branches.view')->get(['id', 'name']),
            'exports' => ReportExport::where('user_id', $user->id)->latest()->limit(20)->get()->map(fn ($export) => [
                ...$export->only('id', 'type', 'status', 'created_at', 'error'),
                'url' => $export->status === 'completed' && $export->expires_at->isFuture() ? URL::temporarySignedRoute('admin.exports.download', $export->expires_at, ['export' => $export->id]) : null,
            ]),
        ]);
    }
}
