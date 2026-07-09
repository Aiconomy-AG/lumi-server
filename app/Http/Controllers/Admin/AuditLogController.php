<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('audit_logs');

        if ($request->has('module')) {
            $query->where('module', $request->query('module'));
        }
        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }

        $logs = $query->orderBy('occurred_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($logs);
    }
}
