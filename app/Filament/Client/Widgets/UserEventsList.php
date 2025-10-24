<?php

namespace App\Filament\Client\Widgets;

use App\Facades\Helper;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class UserEventsList extends Widget
{
    protected static ?string $heading = 'Upcoming Events';
    protected static ?int $sort = 4;
    protected static string $view = 'filament.widgets.user-events-list';
    protected int|string|array $columnSpan = [
        'default' => 12,
        'md'      => 4,
    ];

    /**
     * Holds the processed events.
     * @var \Illuminate\Support\Collection
     */
    public Collection $events;

    // Filter properties
    public string $searchName = '';
    public ?string $filterType = ''; // Use '' for "All" or null
    public ?string $filterMonth = ''; // Use '' for "All" or null. Stores month number e.g., "05"

    // Properties to hold options for dropdowns
    public array $eventTypes = [];
    public array $eventMonths = [];

    /**
     * This method is called when the component is initialized.
     */
    public function mount(): void
    {
        $this->eventTypes = $this->getAvailableEventTypes();
        $this->eventMonths = $this->getAvailableMonths();
        $this->events = $this->getProcessedEvents();
    }

    /**
     * Livewire lifecycle hook that gets called when a public property is updated.
     */
    public function updatedSearchName(): void
    {
        $this->events = $this->getProcessedEvents();
    }

    public function updatedFilterType(): void
    {
        $this->events = $this->getProcessedEvents();
    }

    public function updatedFilterMonth(): void
    {
        $this->events = $this->getProcessedEvents();
    }

    public static function canView(): bool
    {
        $user = Auth::user();
        if (($user->hasRole('Admin') || $user->hasPermissionTo('employees.manage') || $user->hasPermissionTo('employees.view')) && $user && Helper::has_feature('attendance')) {
            return true;
        }

        return false;
    }

    protected function getAvailableEventTypes(): array
    {
        return [
            '' => 'All Events',
            'Birthday' => 'Birthday',
            'Work Anniversary' => 'Work Anniversary',
            'Probation End' => 'Probation End',
        ];
    }

    protected function getAvailableMonths(): array
    {
        $now = Carbon::now();
        $months = ['' => 'Three Months'];
        $months[$now->format('m')] = $now->format('F Y');
        $months[$now->copy()->addMonthNoOverflow()->format('m')] = $now->copy()->addMonthNoOverflow()->format('F Y');
        $months[$now->copy()->addMonthsNoOverflow(2)->format('m')] = $now->copy()->addMonthsNoOverflow(2)->format('F Y');
        return array_unique($months);
    }

    /**
     * Check if any of the dropdown-specific filters are active.
     * Used to show an indicator on the dropdown button.
     */
    public function areDropdownFiltersActive(): bool
    {
        return !empty($this->filterType) || !empty($this->filterMonth);
    }

    /**
     * Resets only the filters within the dropdown.
     */
    public function resetDropdownFilters(): void
    {
        $this->reset(['filterType', 'filterMonth']);
        $this->events = $this->getProcessedEvents();
    }


    protected function getProcessedEvents(): Collection
    {
        $now     = Carbon::now()->startOfDay();
        $dateWindowStart = $now->copy()->subDays(7); // Consider probation ends in the last 7 days
        $dateWindowEnd   = $now->copy()->addMonths(2)->endOfDay(); // Consider probation ends in the next 2 months

        $users = Filament::getTenant()->users()
            ->where('active', 1)
            ->get();

        $processedEvents = collect();

        foreach ($users as $user) {
            $birthDate = $user->date_of_birth ? Carbon::parse($user->date_of_birth) : null;
            $joinDate  = $user->joining_date ? Carbon::parse($user->joining_date) : null;
            $probationEndDate = $user->probation ? Carbon::parse($user->probation)->startOfDay() : null; // Assuming 'probation' column stores the end date

            // Birthday
            if ($birthDate) {
                $nextBirthday = $this->getNextAnnualOccurrence($birthDate, $now);
                if ($nextBirthday && $nextBirthday->between($now, $dateWindowEnd)) { // Only future birthdays in window
                    $processedEvents->push([
                        'name'          => $user->name,
                        'avatar_url'    => $user->getFilamentAvatarUrl(),
                        'type'          => 'Birthday',
                        'date'          => $nextBirthday->format('M d'),
                        'original_date' => $nextBirthday,
                        'days_until'    => $now->diffInDays($nextBirthday, false),
                    ]);
                }
            }

            if ($joinDate) {
                $nextAnniversary = $this->getNextAnnualOccurrence($joinDate, $now);
                if ($nextAnniversary && $nextAnniversary->isSameDay($joinDate) && $nextAnniversary->isSameYear($now) && $now->lt($joinDate)) {
                } elseif ($nextAnniversary && $nextAnniversary->between($now, $dateWindowEnd)) { // Only future anniversaries in window
                    if ($nextAnniversary->isSameDay($joinDate) && $nextAnniversary->isSameDay($now) && $joinDate->isSameDay($now)) {
                    } else {
                        $processedEvents->push([
                            'name'          => $user->name,
                            'avatar_url'    => $user->getFilamentAvatarUrl(),
                            'type'          => 'Work Anniversary',
                            'date'          => $nextAnniversary->format('M d'),
                            'original_date' => $nextAnniversary,
                            'days_until'    => $now->diffInDays($nextAnniversary, false),
                        ]);
                    }
                }
            }

            if ($probationEndDate) {
                if ($probationEndDate->between($dateWindowStart, $dateWindowEnd)) {
                    $processedEvents->push([
                        'name'          => $user->name,
                        'avatar_url'    => $user->getFilamentAvatarUrl(),
                        'type'          => 'Probation End',
                        'date'          => $probationEndDate->format('M d'),
                        'original_date' => $probationEndDate,
                        'days_until'    => $now->diffInDays($probationEndDate, false),
                    ]);
                }
            }
        }

        // Apply Name Filter
        if (!empty($this->searchName)) {
            $processedEvents = $processedEvents->filter(function ($event) {
                return stripos($event['name'], $this->searchName) !== false;
            });
        }

        // Apply Type Filter
        if (!empty($this->filterType)) {
            $processedEvents = $processedEvents->filter(function ($event) {
                return $event['type'] === $this->filterType;
            });
        }

        // Apply Month Filter
        if (!empty($this->filterMonth)) {
            $processedEvents = $processedEvents->filter(function ($event) {
                return $event['original_date']->format('m') === $this->filterMonth;
            });
        }

        return $processedEvents->sortBy(function ($event) {
            // Sort by days_until (ascending), then by name for tie-breaking
            return sprintf('%04d-%s', $event['days_until'] < 0 ? 9000 + $event['days_until'] : $event['days_until'], $event['name']);
        })->values();
    }

    /**
     * Calculates the next occurrence of an annual event (like birthday or work anniversary).
     */
    protected function getNextAnnualOccurrence(?Carbon $date, Carbon $now): ?Carbon
    {
        if (!$date) {
            return null;
        }
        $nextOccurrence = $date->copy()->year($now->year);
        if ($nextOccurrence->lt($now->copy()->startOfDay())) { // Compare with start of day
            $nextOccurrence->addYear();
        }
        return $nextOccurrence->startOfDay(); // Return start of day for consistent comparison
    }
}
