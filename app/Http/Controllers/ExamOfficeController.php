<?php
namespace App\Http\Controllers;

use Inertia\Inertia;

class ExamOfficeController extends Controller
{
    public function dashboard()
    {
        return Inertia::render('ExamOffice/Dashboard');
    }
}
