# How Regular Pay Runs Work

This document details the operational aspects of managing regular pay runs, from creation to finalization and payment.

## 1. Creating a Regular Pay Run

To initiate a new regular pay run, you select the payroll month. The system intelligently determines the available months.

### Key Fields:

-   **Select Payroll Month**: This dropdown allows you to choose the month for the new payroll. If previous pay runs have been finalized, the system automatically suggests the next available month. If no pay runs have been finalized, it will present options based on the company's fiscal year start.
    -   **Important Note**: Once a payroll month is selected and the pay run is created, it cannot be changed.

## 2. Overview of Regular Pay Runs

Regular pay runs are displayed in a table format, providing a summary of each run.

### Table Columns:

-   **Period**: Shows the month and year for which the pay run was conducted (e.g., "July 2025").
-   **No. of Employees**: Indicates the total number of employees included in that specific pay run.
-   **Total Tax**: Displays the sum of all tax deductions for the pay run, formatted with the appropriate currency symbol.
-   **Total Net Pay**: Displays the sum of net payments for all employees in the pay run, formatted with the appropriate currency symbol.
-   **Status**: Shows the current state of the pay run, indicated by a colored badge:
    -   `Draft` (gray)
    -   `Pending Approval` (warning/yellow)
    -   `Rejected` (danger/red)
    -   `Approved` (success/green)
    -   If a pay run is `Rejected`, the reason for rejection is displayed below the status.

## 3. Actions on Regular Pay Runs

Various actions can be performed on regular pay runs, depending on their current status and your permissions.

### Available Actions:

-   **View**: For `Approved` pay runs, a "View" action is available to see the detailed records within that pay run.
-   **Manage**: For pay runs with `Draft`, `Pending Approval`, or `Rejected` statuses, a "Manage" action allows for further editing or review.
-   **Mark as Paid**: For `Approved` pay runs that have not yet been marked as paid, this action allows recording the payment. You will be prompted to select a `Date Paid`.

## 4. Access Control

-   **Creating Pay Runs**: A new pay run can only be created if there are no existing pay runs with `Draft`, `Pending Approval`, or `Rejected` statuses for the current company.
-   **Viewing Pay Runs**: Only users with the `Admin` role, `payroll.manage` permission, or `payroll.approve` permission can view regular pay runs.

This system ensures a structured approach to managing regular payroll cycles.
