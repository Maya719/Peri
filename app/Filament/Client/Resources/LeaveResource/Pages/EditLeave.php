<?php

namespace App\Filament\Client\Resources\LeaveResource\Pages;

use App\Filament\Client\Resources\LeaveResource;
use App\Models\LeaveLog;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EditLeave extends EditRecord
{
    protected static string $resource = LeaveResource::class;

    protected static ?string $title = 'Edit Leave Request';

    public ?string $updateRemarks = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $originalRecord = $this->getRecord();
        $changes = [];

        // Only calculate changes if the editor is NOT the person who requested the leave.
        if (Auth::id() !== $originalRecord->user_id) {
            $fieldsToTrack = [
                'type' => 'Duration',
                'leave_type' => 'Type',
                'paid' => 'Payment Status',
                'starting_date' => 'Starting Date',
                'ending_date' => 'Ending Date',
                'leave_reason' => 'Reason',
            ];

            foreach ($fieldsToTrack as $field => $label) {
                $oldValue = $originalRecord->{$field};
                $newValue = $data[$field] ?? null;

                if ($field === 'paid') {
                    if ((bool)$oldValue !== (bool)$newValue) {
                        $changes[] = "{$label} from '" . ((bool)$oldValue ? 'Paid' : 'Unpaid') . "' to '" . ((bool)$newValue ? 'Paid' : 'Unpaid') . "'";
                    }
                } elseif (in_array($field, ['starting_date', 'ending_date'])) {
                    if ($oldValue && $newValue && !\Carbon\Carbon::parse($oldValue)->isSameDay(\Carbon\Carbon::parse($newValue))) {
                        $changes[] = "{$label} from '{$oldValue}' to '{$newValue}'";
                    }
                } elseif ($oldValue != $newValue) {
                    $changes[] = "{$label} from '{$oldValue}' to '{$newValue}'";
                }
            }

            if (isset($data['document']) && $data['document'] !== $originalRecord->document) {
                $changes[] = "attached document was updated";
            }

            if (!empty($changes)) {
                $editor = Auth::user();
                $this->updateRemarks = "Updated by {$editor->name}: Changed " . implode(', ', $changes) . ".";
            }
        }

        // For half-day and short leave, ensure ending_date equals starting_date
        if (isset($data['type']) && in_array($data['type'], ['half_day', 'short_leave'])) {
            $data['ending_date'] = $data['starting_date'];
        }

        // ... (rest of the method remains the same)
        return $data;
    }

    protected function afterSave(): void
    {
        $approvalStatus = $this->data['approval_status'] ?? 'pending';
        $originalStatus = $this->record->getOriginal('status');

        // Gatekeeper: only act on a real status change from the dropdown
        if ($approvalStatus === 'pending') {
            return;
        }

        $currentUser = Auth::user();
        $currentRole = $currentUser->roles->first();
        $roleId = $currentRole ? $currentRole->id : $currentUser->id;

        // Handle cancellation flow separately
        if ($this->record->status === 'pending_cancellation') {
            if ($approvalStatus == 'rejected_cancellation') {
                $this->record->update(['status' => 'rejected_cancellation']);
                $pendingLog = $this->record->leaveLogs()->where('status', 'Pending Cancellation')->orderBy('level', 'asc')->first();
                LeaveLog::create([
                    'leave_id' => $this->record->id,
                    'role_id'  => $roleId,
                    'level'    => $pendingLog->level + 1,
                    'status'   => 'rejected_cancellation',
                ]);
            } else {
                $this->record->update(['status' => 'cancelled']);
                $pendingLog = $this->record->leaveLogs()->where('status', 'Pending Cancellation')->orderBy('level', 'asc')->first();
                // 3. Update the pending log with the approver's action.
                LeaveLog::create([
                    'leave_id' => $this->record->id,
                    'role_id'  => $roleId,
                    'level'    => $pendingLog->level + 1,
                    'status'   => 'cancelled',
                ]);
            }
            
            return;
        }

        // --- Normal Approval Workflow ---

        // 1. Find the current pending log entry to action.
        $pendingLog = $this->record->leaveLogs()->where('status', 'pending')->orderBy('level', 'asc')->first();

        if (!$pendingLog) {
            // Should not happen in a normal workflow, but good to guard.
            return;
        }

        $currentActionLevel = $pendingLog->level;

        // 2. Combine remarks from field updates and rejection reasons.
        $remarks = $approvalStatus === 'rejected' ? $this->data['rejection_reason'] : null;
        if ($this->updateRemarks) {
            $remarks = $remarks ? $this->updateRemarks . ' | ' . $remarks : $this->updateRemarks;
        }

        // 3. Update the pending log with the approver's action.
        $pendingLog->update([
            'role_id'  => $roleId,
            'status'   => $approvalStatus,
            'remarks'  => $remarks,
        ]);

        // 4. Determine the next step in the hierarchy.
        $leaveUserId = $this->record->user_id;
        $nextLevel = $currentActionLevel + 1;
        $nextStep = DB::table('approval_steps')
            ->where('team_id', Filament::getTenant()->id)
            ->where('user_id', $leaveUserId)
            ->where('level', $nextLevel)
            ->first();

        // 5. Update overall leave status and create a new pending log for the next level.
        if (($approvalStatus === 'forwarded' || $approvalStatus === 'approved') && $nextStep) {
            $this->record->update(['status' => 'forwarded']);
            // Create the next pending log
            LeaveLog::create([
                'leave_id' => $this->record->id,
                'role_id'  => $nextStep->role_id, // Role for the next approver
                'level'    => $nextLevel,
                'status'   => 'pending',
            ]);
        } elseif ($approvalStatus === 'approved' && !$nextStep) {
            // Final approval
            $this->record->update(['status' => 'approved']);
        } elseif ($approvalStatus === 'rejected') {
            // Rejection
            $this->record->update([
                'status' => 'rejected',
                'rejection_reason' => $this->data['rejection_reason'],
            ]);
        }
    }
    protected function beforeSave(): void
    {
        $leaveUserId = $this->data['user_id'];
        $url = LeaveResource::getUrl('edit', ['record' => $this->data['id']]);
        $approvalStatus = $this->data['approval_status'] ?? 'pending';
        if ($approvalStatus !== 'pending') {
            if ($this->record->status === 'pending_cancellation') {
                if ($approvalStatus === 'cancelled') {
                    $message = 'Leave Request cancellation Approved';
                } elseif ($approvalStatus === 'rejected_cancellation') {
                    $message = 'Leave Request cancellation Rejected';
                }
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        $query = request()->getQueryString();

        return static::getResource()::getUrl('index') . ($query ? '?' . $query : '');
    }
}
