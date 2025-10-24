# How to Create One-Time Payments

This document details the steps and considerations for creating one-time payments for employees.

## 1. Accessing the One-Time Payment Form

Navigate to the "One-Time Payment" page within the Payroll Management section.

## 2. Filling Out the Form

The form is divided into sections for employee information, earnings, and deductions.

### Employee Information Section:

-   **Employee(s)**: Select one or more employees who will receive this one-time payment. You can search for employees by name.
-   **Period**: Specify the date range for which this one-time payment is applicable. Use the date range picker to select the start and end dates.
-   **Tax Amount (Applied to Each Employee)**: Enter the tax amount that will be applied to each selected employee's one-time payment. This is a numeric field and defaults to 0.

### Earnings Section:

-   This section allows you to define various earning components for the one-time payment.
-   **Add More**: Click this button to add a new earning row.
-   For each earning row, provide a `Title` (e.g., "Bonus", "Reimbursement") and the `Amount`.
-   You can add multiple earning components as needed.

### Deductions Section:

-   This section allows you to define various deduction components for the one-time payment.
-   **Add More**: Click this button to add a new deduction row.
-   For each deduction row, provide a `Title` (e.g., "Advance", "Loan Repayment") and the `Amount`.
-   You can add multiple deduction components as needed.

## 3. Saving the One-Time Payment

After filling out all the required fields, click the "Save" button to process the one-time payment.

### Important Considerations Before Saving:

-   **Date Range Required**: Ensure that a date range is selected for the payment period.
-   **Employee Selection Required**: At least one employee must be selected.
-   **Conflict Detection**: The system performs checks to prevent conflicts:
    -   **Overlapping One-Time Payments**: It will notify you if an existing one-time payment record for a selected employee overlaps with the specified date range.
    -   **Existing Regular Payroll**: It will notify you if a regular payroll record already exists for a selected employee within the specified date range.
    -   If any conflicts are detected, the payment will not be processed, and an error notification will be displayed.
-   **Status After Saving**: Upon successful saving, the one-time payment will be created with a `pending_approval` status. It will then require approval from an authorized user.

### Error Handling:

-   If any errors occur during the saving process (e.g., invalid date format, database issues), an error notification will be displayed.

## 4. Access Control

-   **Accessing this page**: Only users with the `Admin` role or `payroll.manage` permission can access this page to create one-time payments.

This process ensures that one-time payments are accurately configured and integrated into the payroll system with proper checks and balances.
