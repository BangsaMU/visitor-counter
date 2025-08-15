<?php

namespace Bangsamu\VisitorCounter\Http\Controllers;

use Illuminate\Routing\Controller;
use Bangsamu\VisitorCounter\Models\Visitor;
use Illuminate\Support\Facades\DB;

class VisitorApiController extends Controller
{

    public function index()
    {
        // Contoh ambil total visitor harian 7 hari terakhir
        $stats = Visitor::selectRaw('visit_date, COUNT(*) as total')
            ->groupBy('visit_date')
            ->orderBy('visit_date', 'desc')
            ->limit(7)
            ->get();

        return view('visitor-counter::dashboard', [
            'stats' => $stats,
            'totalVisitors' => Visitor::count(),
            'uniqueVisitors' => Visitor::distinct('ip')->count('ip'),
        ]);
    }

    public function stats()
    {
        $data = Visitor::select(
                DB::raw('visit_date'),
                DB::raw('COUNT(DISTINCT ip) as unique_visitors'),
                DB::raw('COUNT(*) as total_hits')
            )
            ->groupBy('visit_date')
            ->orderBy('visit_date', 'asc')
            ->get();

        return response()->json($data);
    }

    public function today()
    {
        $today = now()->toDateString();

        return response()->json([
            'unique_visitors' => Visitor::where('visit_date', $today)->distinct('ip')->count(),
            'total_hits'      => Visitor::where('visit_date', $today)->count(),
        ]);
    }
}
