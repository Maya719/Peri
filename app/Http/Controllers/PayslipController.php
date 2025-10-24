<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use Illuminate\Contracts\View\View;

class PayslipController extends Controller
{
    public function show(Payroll $payroll): View
    {
        return view('livewire.view-payslip', [
            'payroll' => $payroll,
        ]);
    }
}
