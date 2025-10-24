<?php

namespace App\Filament\Client\Resources\PayRunResource\Pages;

use App\Filament\Client\Resources\PayRunResource;
use App\Models\Payroll;
use App\Models\PayRun;
use App\Services\AppService;
use App\Services\HelperService;
use App\Services\LeaveService;
use App\Services\PayrollCalculationService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreatePayRun extends CreateRecord
{
    protected static string $resource = PayRunResource::class;
    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    public function getTitle(): string
    {
        return 'Create Pay Run';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $parsedDate = Carbon::createFromDate((int)$data['year'], (int)$data['month'], 1);

        $data['year'] = $parsedDate->year;
        $data['month'] = $parsedDate->month;
        $data['pay_period_start_date'] = $parsedDate->copy()->startOfMonth();
        $data['pay_period_end_date'] = $parsedDate->copy()->endOfMonth();
        $data['status'] = 'draft';

        $adminId = Filament::getTenant()->id;
        $data['initiated_by'] = $adminId;
        $data['team_id'] = $adminId;
        $data['total_employees'] = Filament::getTenant()
            ->users()
            ->where('active', true)
            ->whereHas('bankDetails', function ($q) {
                $q->where('team_id', Filament::getTenant()->id)
                    ->where('active', true)
                    ->where('base_salary', '>', 0);
            })->count();

        $payrollCalculationService = new PayrollCalculationService(app(HelperService::class), app(LeaveService::class));
        $data['currency_symbol'] = $payrollCalculationService->getCurrencySymbolForAdmin($adminId);

        return $data;
    }

    protected function handleRecordCreation(array $data): PayRun
    {
        $payRun = PayRun::create($data);

        $payPeriodStartDate = Carbon::parse($payRun->pay_period_start_date);
        $payPeriodEndDate = Carbon::parse($payRun->pay_period_end_date);
        $currentPeriodMonth = $payPeriodStartDate->month;
        $payrollCalculationService = new PayrollCalculationService(app(HelperService::class), app(LeaveService::class));

        $activeEmployees =
            Filament::getTenant()->users()
            ->whereHas('bankDetails', function ($q) {
                $q->where('team_id', Filament::getTenant()->id)
                ->where('base_salary', '>', 0);
            })
            ->where('active', true)
            ->whereDate('joining_date', '<=', $payPeriodEndDate)
            ->get();

        DB::beginTransaction();
        try {
            foreach ($activeEmployees as $employee) {
                $payrollData = $payrollCalculationService->calculateEmployeePayrollData(
                    $employee->id,
                    $payPeriodStartDate,
                    $payPeriodEndDate,
                    $currentPeriodMonth,
                    null,
                    [],
                    [],
                    false,
                    'number',
                    null,
                    true,
                    true,
                    true,
                    'non-taxable',
                    'taxable'
                );
                Payroll::create(array_merge($payrollData, [
                    'user_id' => $employee->id,
                    'pay_run_id' => $payRun->id,
                    'date_range_start' => $payRun->pay_period_start_date,
                    'date_range_end' => $payRun->pay_period_end_date,
                    'month_indicator' => $payrollData['month_indicator'],
                    'status' => false,
                    'payment_mode' => $employee->default_payment_mode ?? 'bank',
                ]));
            }
            DB::commit();
            // Notification::make()->success()->title('Pay Run Initiated')->body('Draft payrolls generated for ' . $activeEmployees->count() . ' employees.')->send();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->danger()->title('Error Initiating Payroll')->body($e->getMessage())->send();
        }
        return $payRun;
    }
}
