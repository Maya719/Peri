<?php

namespace App\Filament\Client\Resources\EmployeeResource\Pages;

use App\Filament\Client\Resources\EmployeeResource;
use App\Models\DepartmentUser;
use App\Models\Role;
use App\Models\ShiftUser;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->url(fn() => $this->getResource()::getUrl())
                ->icon('heroicon-o-arrow-left')
                ->color('gray'),

            ...parent::getHeaderActions(),

            Actions\DeleteAction::make('detach')
                ->label('Delete Account')
                ->action(fn() => Filament::getTenant()->members()->detach($this->record))
                ->after(function () {
                    $tenant = \Filament\Facades\Filament::getTenant();
                    $subscription = $tenant->activePlanSubscriptions()->first();
                    if ($subscription) {
                        $subscription->reduceFeatureUsage('employees');
                    }
                    redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function beforeSave(): void
    {
        $role = Role::find($this->data["role"]);
        $this->record->syncRoles($role);
    }
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $tenant = Filament::getTenant();

        $shiftAssignment = ShiftUser::where('team_id', $tenant->id)
            ->where('user_id', $this->record->id)
            ->first();

        $data['shift_id'] = $shiftAssignment?->shift_id;

        $DepartmentAssignment = Filament::getTenant()->departmentUsers()
            ->where('user_id', $this->record->id)
            ->first();

        $data['department_id'] = $DepartmentAssignment?->department_id;

        $bankDetail = $this->record->bankDetails()
            ->where('team_id', $tenant->id)
            ->first();

        $data['bank_details'] = $bankDetail ? $bankDetail->toArray() : [];

        $data['funds_ids'] = $this->record->funds()
            ->wherePivot('team_id', $tenant->id)
            ->pluck('fund_id')
            ->toArray();
        return $data;
    }
    protected function afterSave(): void
    {
        $tenant = Filament::getTenant();

        if ($this->data["shift_id"]) {
            ShiftUser::updateOrCreate(
                [
                    'team_id' => $tenant->id,
                    'user_id' => $this->record->id,
                ],
                [
                    'shift_id' => $this->data["shift_id"],
                ]
            );
        } else {
            ShiftUser::where('team_id', $tenant->id)
                ->where('user_id', $this->record->id)
                ->delete();
        }

        if ($this->data["department_id"]) {
            DepartmentUser::updateOrCreate(
                [
                    'team_id' => $tenant->id,
                    'user_id' => $this->record->id,
                ],
                [
                    'department_id' => $this->data["department_id"],
                ]
            );
        }

        $bankDetails = $this->data['bank_details'] ?? [];
        $bankDetails['base_salary'] = $bankDetails['base_salary'] !== '' ? $bankDetails['base_salary'] : null;


        if (($bankDetails['payment_method'] ?? null) !== 'bank_transfer') {
            $bankDetails['account_holder_name'] = null;
            $bankDetails['account_number'] = null;
            $bankDetails['bank_name'] = null;
            $bankDetails['base_salary'] = $bankDetails['base_salary'] !== '' ? $bankDetails['base_salary'] : null;
        }

        unset($this->data['bank_details']);

        $this->record->bankDetails()
            ->updateOrCreate(
                ['team_id' => $tenant->id],
                $bankDetails
            );

        $fundIds = $this->data['funds_ids'] ?? [];

        $this->record->funds()->detach();

        foreach ($fundIds as $fundId) {
            $this->record->funds()->attach($fundId, ['team_id' => $tenant->id]);
        }
    }
}
