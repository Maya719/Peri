{{-- Save as resources/views/components/approval-flow.blade.php --}}
<style>
    .approval-container {
        overflow-x: auto;
        padding: 1rem 0;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.5);
    }

    .approval-steps {
        display: flex;
        align-items: center;
        gap: 0;
        padding: 0 2rem;
        min-width: fit-content;
    }

    .approval-step {
        position: relative;
        flex: 0 0 170px;
        min-height: 100px;
        border: 2px solid transparent;
        border-radius: 12px;
        background: white;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0.75rem 0.5rem;
        box-sizing: border-box;
        text-align: center;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        backdrop-filter: blur(10px);
        transform: translateY(0);
    }

    .approval-step:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .step-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .approval-connector {
        flex: 1;
        height: 4px;
        background: linear-gradient(90deg, transparent 0%, #e2e8f0 20%, #e2e8f0 80%, transparent 100%);
        position: relative;
        margin: 0 -1px;
    }

    .approval-connector::before {
        content: '';
        position: absolute;
        right: -8px;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 0;
        border-left: 8px solid #e2e8f0;
        border-top: 6px solid transparent;
        border-bottom: 6px solid transparent;
    }

    .step-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex-grow: 1;
        width: 100%;
    }

    .step-name {
        font-weight: 700;
        font-size: 0.85rem;
        color: #1e293b;
        line-height: 1.3;
        margin-bottom: 0.25rem;
        text-align: center;
        word-wrap: break-word;
        hyphens: auto;
    }

    .step-status {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.2rem 0.5rem;
        border-radius: 16px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
        transition: all 0.3s ease;
    }

    .step-date {
        font-size: 0.65rem;
        color: #64748b;
        font-weight: 500;
        opacity: 0.8;
    }

    .step-progress {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #22c55e, #16a34a);
        border-radius: 16px 16px 0 0;
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.6s ease;
    }

    /* Dark mode */
    .dark .approval-container {
        background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
        border-color: rgba(55, 65, 81, 0.5);
    }

    .dark .approval-step {
        background: rgba(31, 41, 55, 0.8);
        backdrop-filter: blur(10px);
    }

    .dark .step-name {
        color: #f3f4f6;
    }

    .dark .approval-connector {
        background: linear-gradient(90deg, transparent 0%, #374151 20%, #374151 80%, transparent 100%);
    }

    .dark .approval-connector::before {
        border-left-color: #374151;
    }

    .dark .step-date {
        color: #9ca3af;
    }

    /* Status variants with enhanced styling */
    .status-positive {
        border-color: #22c55e;
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    }

    .status-positive .step-icon {
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: white;
    }

    .status-positive .step-name {
        color: #15803d;
    }

    .status-positive .step-status {
        background: rgba(34, 197, 94, 0.1);
        color: #22c55e;
        border: 1px solid rgba(34, 197, 94, 0.2);
    }

    .status-positive .step-progress {
        transform: scaleX(1);
    }

    .status-positive .approval-connector {
        background: linear-gradient(90deg, transparent 0%, #22c55e 20%, #22c55e 80%, transparent 100%);
    }

    .status-positive .approval-connector::before {
        border-left-color: #22c55e;
    }

    .status-active {
        border-color: #f59e0b;
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        animation: pulse 2s infinite;
    }

    .status-active .step-icon {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        animation: bounce 2s infinite;
    }

    .status-active .step-name {
        color: #a16207;
    }

    .status-active .step-status {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .status-neutral {
        border-color: #e2e8f0;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .status-neutral .step-icon {
        background: linear-gradient(135deg, #64748b, #475569);
        color: white;
    }

    .status-neutral .step-name {
        color: #475569;
    }

    .status-neutral .step-status {
        background: rgba(100, 116, 139, 0.1);
        color: #64748b;
        border: 1px solid rgba(100, 116, 139, 0.2);
    }

    .status-negative {
        border-color: #ef4444;
        background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
    }

    .status-negative .step-icon {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .status-negative .step-name {
        color: #b91c1c;
    }

    .status-negative .step-status {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    /* Dark mode status variants */
    .dark .status-positive {
        background: rgba(34, 197, 94, 0.1);
        border-color: #22c55e;
    }

    .dark .status-positive .step-name {
        color: #4ade80;
    }

    .dark .status-active {
        background: rgba(245, 158, 11, 0.1);
        border-color: #f59e0b;
    }

    .dark .status-active .step-name {
        color: #fbbf24;
    }

    .dark .status-neutral {
        background: rgba(100, 116, 139, 0.1);
        border-color: #374151;
    }

    .dark .status-neutral .step-name {
        color: #9ca3af;
    }

    .dark .status-negative {
        background: rgba(239, 68, 68, 0.1);
        border-color: #ef4444;
    }

    .dark .status-negative .step-name {
        color: #f87171;
    }

    /* Animations */
    @keyframes pulse {
        0%, 100% {
            transform: translateY(0) scale(1);
        }
        50% {
            transform: translateY(-2px) scale(1.02);
        }
    }

    @keyframes bounce {
        0%, 20%, 53%, 80%, 100% {
            transform: translateY(0);
        }
        40%, 43% {
            transform: translateY(-4px);
        }
        70% {
            transform: translateY(-2px);
        }
        90% {
            transform: translateY(-1px);
        }
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .approval-container {
            padding: 1rem 0;
        }
        
        .approval-steps {
            padding: 0 1rem;
        }
        
        .approval-step {
            flex: 0 0 120px;
            min-height: 90px;
            padding: 0.5rem 0.4rem;
        }
        
        .step-icon {
            width: 28px;
            height: 28px;
            font-size: 0.8rem;
        }
        
        .step-name {
            font-size: 0.75rem;
        }
        
        .step-status {
            font-size: 0.6rem;
            padding: 0.15rem 0.4rem;
        }
        
        .step-date {
            font-size: 0.6rem;
        }
    }
</style>

@php
    $hasLeave = isset($leave) && $leave?->id;
    $logsByLevel = $hasLeave ? $logs->keyBy('level') : collect();
    $finalStatus = $hasLeave ? strtolower($leave->status) : 'pending';
    $rejectionLog = $logsByLevel->firstWhere('status', 'rejected');
    
    // Define status icons
    $statusIcons = [
        'submitted' => '✓',
        'draft' => '✎',
        'pending' => '⏳',
        'approved' => '✓',
        'forwarded' => '→',
        'rejected' => '✗',
        'not_reached' => '○',
        'pending_action' => '⚡'
    ];
@endphp

<div class="approval-container">
    <div class="approval-steps">

        <!-- 1. Requestor Step -->
        @php
            $requestorStatusClass = $hasLeave ? 'status-positive' : 'status-active';
            $requestorStatusLabel = $hasLeave ? 'Submitted' : 'Draft';
            $requestorDate = $hasLeave ? $leave->created_at->format('M d, Y') : null;
            $requestorIcon = $hasLeave ? $statusIcons['submitted'] : $statusIcons['draft'];
        @endphp
        <div class="approval-step {{ $requestorStatusClass }}">
            <div class="step-progress"></div>
            <div class="step-content">
                <div class="step-icon">{{ $requestorIcon }}</div>
                <div class="step-name">{{ $leaveUser->name }}</div>
                <div class="step-status">{{ $requestorStatusLabel }}</div>
                @if ($requestorDate)
                    <div class="step-date">{{ $requestorDate }}</div>
                @endif
            </div>
        </div>

        @if ($hierarchySteps->isNotEmpty())
            <div class="approval-connector"></div>
        @endif

        <!-- 2. Approval Hierarchy Steps -->
        @foreach ($hierarchySteps as $index => $step)
            @php
                $level = $step->level;
                $log = $logsByLevel->get($level);
                $status = $log ? strtolower($log->status) : 'pending';
                $roleName = \App\Models\Role::find($step->role_id)?->name ?? 'Role not found';
                $actionDate = $log && !in_array($status, ['pending']) ? $log->created_at->format('M d, Y') : null;

                $statusClass = 'status-neutral';
                $statusLabel = 'Pending';
                $statusIcon = $statusIcons['pending'];

                if ($rejectionLog) {
                    if ($level < $rejectionLog->level) {
                        $statusClass = 'status-positive';
                        $statusLabel = 'Forwarded';
                        $statusIcon = $statusIcons['forwarded'];
                    } elseif ($level == $rejectionLog->level) {
                        $statusClass = 'status-negative';
                        $statusLabel = 'Rejected';
                        $statusIcon = $statusIcons['rejected'];
                    } else {
                        $statusClass = 'status-neutral';
                        $statusLabel = 'Not Reached';
                        $statusIcon = $statusIcons['not_reached'];
                    }
                } elseif ($finalStatus === 'approved') {
                    $statusClass = 'status-positive';
                    $statusLabel = 'Approved';
                    $statusIcon = $statusIcons['approved'];
                } else {
                    $currentPendingLevel = $logsByLevel->where('status', 'pending')->min('level');
                    if ($status === 'forwarded' || $status === 'approved') {
                        $statusClass = 'status-positive';
                        $statusLabel = ucfirst($status);
                        $statusIcon = $statusIcons[$status];
                    } elseif ($level == $currentPendingLevel) {
                        $statusClass = 'status-active';
                        $statusLabel = 'Pending Action';
                        $statusIcon = $statusIcons['pending_action'];
                    } elseif ($level > $currentPendingLevel) {
                        $statusClass = 'status-neutral';
                        $statusLabel = 'Pending';
                        $statusIcon = $statusIcons['pending'];
                    }
                }
            @endphp

            <div class="approval-step {{ $statusClass }}">
                <div class="step-progress"></div>
                <div class="step-content">
                    <div class="step-icon">{{ $statusIcon }}</div>
                    <div class="step-name">{{ $roleName }}</div>
                    <div class="step-status">{{ $statusLabel }}</div>
                    @if ($actionDate)
                        <div class="step-date">{{ $actionDate }}</div>
                    @endif
                </div>
            </div>

            @if ($index < $hierarchySteps->count() - 1)
                <div class="approval-connector"></div>
            @endif
        @endforeach

    </div>
</div>