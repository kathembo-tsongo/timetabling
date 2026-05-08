<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CSPSolverController extends Controller
{
    public function optimize(Request $request)
    {
        return response()->json([
            'message' => 'CSP Solver endpoint',
            'status'  => 'pending_implementation'
        ]);
    }
}
