# How Payslips Work

This document details the operational aspects of the Payslips Management System, from viewing individual payslips to exporting and bulk downloading payroll data.

## 1. Overview of Payslip Records

Payslip records are displayed in a table format, organized by payroll period.

### Table Columns:

-   **Employee**: The name of the employee.
-   **Department**: The department the employee is assigned to.
-   **Shift**: The shift the employee is assigned to.
-   **Tax**: The calculated tax for the payroll period, formatted with the appropriate currency symbol.
-   **Net Pay**: The net payable salary for the payroll period, formatted with the appropriate currency symbol.

### Grouping:

-   Payslips are automatically grouped by `Period` (e.g., "July 2025"), making it easy to navigate through different payroll cycles.

### Filtering:

-   **Period**: Filter payslips by a specific month and year.
-   **Employee**: Search and filter payslips for a particular employee.
-   **Department**: Filter payslips by department.
-   **Shift**: Filter payslips by shift.

## 2. Actions on Payslip Records

Various actions can be performed on payslip records, depending on your permissions.

### Available Actions:

-   **View**: This action allows you to view a detailed breakdown of a specific payslip. It opens a modal window displaying all earnings, deductions, and other payroll components.
-   **Download**: This action allows you to download an individual payslip as a PDF file.

## 3. Exporting Payroll Data

Administrators and users with payroll approval permissions can export payroll data to an Excel file.

### Export Payroll Feature:

-   **Export Payroll Button**: Located in the header, this button allows you to export a summary of payroll data for a selected month.
-   **Selection**: You will be prompted to select a `Month` for which you want to export the payroll data.
-   **Output**: The system generates an Excel file containing `Employee Name` and `Net Salary` for all approved payrolls in the selected month.

## 4. Bulk Downloading Payslips

Administrators and users with payroll record management or approval permissions can download multiple payslips at once.

### Download Payslips Feature:

-   **Download Payslips Button**: Located in the header, this button allows you to download a ZIP archive containing PDF payslips for a selected month.
-   **Selection**: You will be prompted to select a `Month` for which you want to download the payslips.
-   **Output**: The system generates a ZIP file containing individual PDF payslips for each employee in the selected month.

## 5. Access Control

-   **Viewing Payslips**: Only users with the `Admin` role, `payroll.viewRecords` permission, `payroll.manage` permission, or `payroll.approve` permission can view payslips.
-   **Exporting Payroll**: Only users with the `Admin` role or `payroll.approve` permission can export payroll data.
-   **Downloading Payslips**: Only users with the `Admin` role, `payroll.manage` permission, or `payroll.approve` permission can download payslips in bulk.

This system provides comprehensive tools for managing and accessing payroll information.
