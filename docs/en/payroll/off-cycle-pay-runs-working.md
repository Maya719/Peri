# How One-Time Pay Runs Work

This document details the operational aspects of managing one-time pay runs, from viewing their status to taking action on them.

## 1. Overview of One-Time Pay Runs

One-time pay runs are displayed in a table format, providing a summary of each run.

### Table Columns:

-   **Period**: Shows the month and year for which the one-time pay run was conducted (e.g., "July 2025").
-   **No. of Employees**: Indicates the total number of employees included in that specific pay run.
-   **Total Net Pay**: Displays the sum of net payments for all employees in the pay run, formatted with the appropriate currency symbol.
-   **Status**: Shows the current state of the pay run, indicated by a colored badge:
    -   `Pending Approval` (warning/yellow)
    -   `Approved` (success/green)
    -   `Rejected` (danger/red)
    -   If a pay run is `Rejected`, the reason for rejection is displayed below the status.

### Filtering:

-   The table can be filtered by `Status` to quickly find pay runs that are pending, approved, or rejected.

## 2. Actions on One-Time Pay Runs

Various actions can be performed on one-time pay runs, depending on their current status and your permissions.

### Available Actions:

-   **View**: For `Approved` pay runs, a "View" action is available to see the detailed records within that pay run.
-   **Manage**: For pay runs with `Draft`, `Pending Approval`, or `Rejected` statuses, a "Manage" action allows for further editing or review.
-   **Approve**: If a pay run is `Pending Approval`, authorized users can `Approve` it. This action requires confirmation and will update the status of the pay run and all associated employee payrolls to `Approved`.
-   **Reject**: If a pay run is `Pending Approval`, authorized users can `Reject` it. This action requires providing a `Reason for Rejection` and will update the status of the pay run and all associated employee payrolls to `Rejected`.
-   **Delete**: Pay runs that are not yet `Approved` can be `Deleted`.
-   **Mark as Paid**: For `Approved` pay runs that have not yet been marked as paid, this action allows recording the payment. You will be prompted to select a `Date Paid`.

### Bulk Actions:

-   Multiple pay runs can be selected and `Deleted` in bulk, provided they are not yet `Approved`.

## 3. Access Control

-   **Viewing One-Time Pay Runs**: Only users with the `Admin` role, `payroll.approve` permission, or `payroll.manage` permission can view one-time pay runs.
-   **Creating One-Time Pay Runs**: Creation of one-time pay runs is handled through a separate process and is not directly available from this interface.
