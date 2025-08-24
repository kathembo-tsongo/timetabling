<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;

class ClassUnitController extends Controller
{
    public function getUnits(ClassModel $class)
    {
        $units = $class->units()->with('program')->get();
        return response()->json($units);
    }
}
