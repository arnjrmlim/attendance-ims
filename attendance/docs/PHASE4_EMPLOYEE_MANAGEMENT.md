# Phase 4 - Employee Management Module

## Overview

The Employee Management Module provides comprehensive employee profile management, including registration, editing, archiving, status management, QR code generation, and attendance credentials. This module integrates seamlessly with the existing Attendance Management System from Phases 1-3.

## Installation

### Database Migration

Run the Phase 4 migration to add new employee fields and related tables:

```bash
mysql -u root -p attendance_db < database/migrations/phase4.sql
```

### Migration Details

The Phase 4 migration includes:

1. **Expanded Employees Table**
   - Added personal information fields (gender, date_of_birth, civil_status, nationality)
   - Added contact information fields (alternate_mobile, home_address)
   - Added emergency contact fields (emergency_contact_name, emergency_contact_number, emergency_contact_relationship)
   - Added employment fields (employment_type, immediate_supervisor_id)
   - Added audit fields (archived_at, archived_by, created_by, updated_by)
   - Updated employment_status enum to include: Active, Inactive, Suspended, Resigned, Terminated, Retired
   - Updated employment_type enum to include: Regular, Probationary, Contractual, Part-Time, Temporary, Intern

2. **New Tables**
   - `employee_timeline` - Tracks all employee-related events and changes
   - `employee_imports` - Tracks employee import operations

3. **Permissions**
   - Added 10 new employee-related permissions
   - Granted appropriate permissions to Administrator, HR, and Employee roles

4. **Settings**
   - Added employee-related system settings (photo limits, QR code settings)

## Features

### Employee Registration

**Route:** `GET /employees/create`  
**Controller:** `EmployeeController::create`

The employee registration form includes:

- **Personal Information**
  - Employee Number (unique, required)
  - First Name (required)
  - Middle Name
  - Last Name (required)
  - Suffix
  - Gender
  - Date of Birth
  - Civil Status
  - Nationality
  - Profile Photo (upload with validation)

- **Contact Information**
  - Mobile Number (required)
  - Alternate Mobile Number
  - Email Address (required, unique)
  - Home Address

- **Emergency Contact**
  - Emergency Contact Name (required)
  - Emergency Contact Number (required)
  - Emergency Contact Relationship (required)

- **Employment Information**
  - Department (required)
  - Branch (required)
  - Position (required)
  - Employment Status (required)
  - Employment Type (required)
  - Date Hired (required)
  - Immediate Supervisor
  - Assigned Shift (required)

- **Attendance Credentials**
  - Employee PIN (required, unique, 4-10 digits)
  - RFID Number (optional)
  - Account Status (active/inactive)

**Validation:**
- Duplicate employee number check
- Duplicate email check
- Duplicate PIN check
- Duplicate RFID check
- Email format validation
- Mobile number format validation
- Date validation
- Required field validation
- Character limit validation
- Photo file type and size validation (max 2MB, JPG/PNG)

### Employee List

**Route:** `GET /employees`  
**Controller:** `EmployeeController::index`

Features:
- Paginated employee listing (15 per page)
- Search by employee number or name
- Filter by department, branch, shift, employment status, employment type
- Bulk actions (activate, deactivate, archive)
- Export to CSV
- Import from Excel (placeholder for future implementation)
- Quick actions per employee (view, edit, print QR, activate/deactivate, archive)
- Employee statistics cards (total, active, inactive, recent hires)

### Employee Profile

**Route:** `GET /employees/show?id={employee_id}`  
**Controller:** `EmployeeController::show`

Displays:
- Profile photo
- Personal information
- Contact information
- Emergency contact
- Employment details
- Attendance credentials
- QR code with print option
- Activity timeline
- Quick action buttons (edit, activate/deactivate, archive)

### Employee Edit

**Route:** `GET /employees/edit?id={employee_id}`  
**Controller:** `EmployeeController::edit`

Allows updating all employee fields with the same validation as registration. Photo can be replaced, and PIN can be changed (leave blank to keep current).

### Employee Status Management

**Statuses Available:**
- Active
- Inactive
- Suspended
- Resigned
- Terminated
- Retired

**Business Rules:**
- Inactive employees cannot record attendance
- Suspended employees cannot record attendance
- Resigned employees remain in historical records
- Archived employees are hidden from default searches but recoverable

**Routes:**
- `POST /employees/activate` - Activate employee
- `POST /employees/deactivate` - Deactivate employee
- `POST /employees/change-status` - Change employment status
- `POST /employees/archive` - Archive employee (soft delete)
- `POST /employees/restore` - Restore archived employee

### QR Code Generation

**Features:**
- Automatic unique QR code generation on employee creation
- QR code value format: `EMP-{16-character-hex}`
- View QR code in profile
- Print QR code (printable page)
- Regenerate QR code (invalidates old QR)

**Routes:**
- `GET /employees/print-qr?id={employee_id}` - Print QR code page
- `POST /employees/regenerate-qr` - Regenerate QR code

### Employee Timeline

**Tracked Events:**
- employee_created
- employee_updated
- employee_archived
- employee_restored
- employee_activated
- employee_deactivated
- status_changed
- department_changed
- branch_changed
- shift_changed
- position_changed
- photo_updated
- qr_code_regenerated
- imported
- attendance_milestone

Each timeline entry includes:
- Event type
- Previous value (JSON)
- New value (JSON)
- Description
- Created by (user)
- Timestamp

### Bulk Actions

**Supported Operations:**
- Bulk Activate
- Bulk Deactivate
- Bulk Archive

**Route:** `POST /employees/bulk-action`

### Export

**Route:** `GET /employees/export`  
**Format:** CSV

Export includes all employee fields with related data (department name, branch name, shift name). Supports filtering via query parameters.

### Import

**Route:** `GET /employees/import` (form), `POST /employees/import` (process)

**Status:** Placeholder for future implementation with PhpSpreadsheet library.

**Planned Features:**
- Excel (.xlsx) file upload
- Validation of employee numbers, emails, PINs
- Department/branch/shift validation
- Import preview
- Error report for failed rows
- Partial import support

## Security Features

- **PDO Prepared Statements** - All database queries use prepared statements
- **Server-side Validation** - Comprehensive validation on all inputs
- **CSRF Protection** - All forms include CSRF tokens
- **XSS Prevention** - Output escaping using `e()` helper
- **SQL Injection Protection** - Parameterized queries
- **Secure File Upload** - MIME type validation, file size limits, randomized filenames
- **Input Sanitization** - Trim and validate all inputs
- **Output Escaping** - HTML escaping for all output
- **Audit Trail** - All employee actions logged to audit_logs table
- **Role-based Access** - Administrator and HR roles only

## File Structure

```
attendance/
├── app/
│   ├── controllers/
│   │   └── EmployeeController.php
│   ├── services/
│   │   └── EmployeeService.php
│   └── views/
│       └── employees/
│           ├── index.php
│           ├── create.php
│           ├── edit.php
│           ├── view.php
│           ├── print-qr.php
│           └── import.php
├── database/
│   └── migrations/
│       └── phase4.sql
└── routes/
    └── web.php (updated)
```

## API Endpoints

| Method | Route | Controller | Description |
|--------|-------|------------|-------------|
| GET | /employees | EmployeeController::index | Employee list |
| GET | /employees/create | EmployeeController::create | Registration form |
| POST | /employees | EmployeeController::store | Create employee |
| GET | /employees/show | EmployeeController::show | Employee profile |
| GET | /employees/edit | EmployeeController::edit | Edit form |
| POST | /employees/update | EmployeeController::update | Update employee |
| POST | /employees/archive | EmployeeController::archive | Archive employee |
| POST | /employees/restore | EmployeeController::restore | Restore employee |
| POST | /employees/activate | EmployeeController::activate | Activate employee |
| POST | /employees/deactivate | EmployeeController::deactivate | Deactivate employee |
| POST | /employees/change-status | EmployeeController::changeStatus | Change employment status |
| POST | /employees/regenerate-qr | EmployeeController::regenerateQR | Regenerate QR code |
| POST | /employees/bulk-action | EmployeeController::bulkAction | Bulk operations |
| GET | /employees/export | EmployeeController::export | Export CSV |
| GET | /employees/print-qr | EmployeeController::printQR | Print QR code |
| GET | /employees/import | EmployeeController::importForm | Import form |
| POST | /employees/import | EmployeeController::import | Process import |

## Permissions

The following permissions are added in Phase 4:

- `employees.view` - View employee list and profiles
- `employees.create` - Add new employees
- `employees.edit` - Edit employee information
- `employees.delete` - Archive/restore employees
- `employees.status` - Activate/deactivate/suspend employees
- `employees.import` - Import employees from Excel
- `employees.export` - Export employee data
- `employees.timeline` - View employee activity timeline
- `employees.photos` - Upload and manage employee photos
- `employees.qr_regenerate` - Regenerate employee QR codes

**Role Assignments:**
- **Administrator** - All permissions
- **HR** - All permissions
- **Employee** - employees.view only

## Integration with Existing System

### Dashboard Integration

The dashboard now includes enhanced employee statistics:
- Total Employees
- Active Employees
- Inactive Employees
- Suspended Employees
- Resigned Employees
- Recent Hires (30 days)

### Audit Trail Integration

All employee actions are logged to the `audit_logs` table with:
- User ID and username
- Action performed
- Module (employees)
- Record ID
- Previous and new values
- Computer name
- IP address
- User agent
- Timestamp

### Timeline Integration

Employee timeline entries are automatically created for:
- Profile creation and updates
- Status changes
- Department/branch/shift changes
- Photo updates
- QR code regeneration
- Import operations

## Future Enhancements

### Planned Features
1. **Full Excel Import** - Implement PhpSpreadsheet for robust Excel import
2. **PDF Export** - Add PDF export option using a PDF library
3. **QR Code Library** - Integrate a proper QR code generation library (e.g., endroid/qr-code)
4. **Employee ID Card** - Generate printable ID cards with photos and QR codes
5. **Profile Completion** - Calculate and display profile completion percentage
6. **Department/Branch Counters** - Show employee counts per department/branch
7. **Advanced Search** - Full-text search across all employee fields
8. **Photo Gallery** - Grid view of employee photos
9. **Bulk Import Template** - Auto-generate Excel import template
10. **Attendance Summary in Profile** - Show attendance statistics in employee profile

### Nice-to-Have Features
- Employee photo cropping
- Drag-and-drop photo upload with progress indicator
- Employee statistics charts
- Organization chart visualization
- Employee onboarding workflow
- Document attachments (contracts, etc.)
- Skills and certifications tracking
- Performance review integration

## Troubleshooting

### Photo Upload Issues

If photo uploads fail:
1. Check upload directory permissions (`uploads/photos/`)
2. Verify PHP upload limits in php.ini
3. Ensure file type validation is working
4. Check available disk space

### QR Code Issues

The current implementation uses a placeholder SVG. For production use:
1. Install a QR code library (e.g., `composer require endroid/qr-code`)
2. Update the QR code generation in `EmployeeService`
3. Update the QR code display in views

### Import Issues

The import feature is currently a placeholder. To enable:
1. Install PhpSpreadsheet: `composer require phpoffice/phpspreadsheet`
2. Implement the import logic in `EmployeeService::importFromExcel()`
3. Update the controller to process the import

## Database Schema Changes

### Employees Table Additions

```sql
ALTER TABLE employees
  ADD COLUMN gender ENUM('Male','Female','Other') DEFAULT NULL,
  ADD COLUMN date_of_birth DATE DEFAULT NULL,
  ADD COLUMN civil_status ENUM('Single','Married','Widowed','Separated','Divorced') DEFAULT NULL,
  ADD COLUMN nationality VARCHAR(80) DEFAULT NULL,
  ADD COLUMN alternate_mobile VARCHAR(30) DEFAULT NULL,
  ADD COLUMN home_address VARCHAR(500) DEFAULT NULL,
  ADD COLUMN emergency_contact_name VARCHAR(120) DEFAULT NULL,
  ADD COLUMN emergency_contact_number VARCHAR(30) DEFAULT NULL,
  ADD COLUMN emergency_contact_relationship VARCHAR(50) DEFAULT NULL,
  ADD COLUMN employment_type ENUM('Regular','Probationary','Contractual','Part-Time','Temporary','Intern') DEFAULT 'Probationary',
  ADD COLUMN immediate_supervisor_id CHAR(36) DEFAULT NULL,
  ADD COLUMN archived_at DATETIME DEFAULT NULL,
  ADD COLUMN archived_by CHAR(36) DEFAULT NULL,
  ADD COLUMN created_by CHAR(36) DEFAULT NULL,
  ADD COLUMN updated_by CHAR(36) DEFAULT NULL;
```

### New Tables

**employee_timeline:**
- Tracks all employee-related events
- Links to employee and user tables
- Stores previous and new values as JSON

**employee_imports:**
- Tracks import operations
- Stores success/failure counts
- Stores error reports

## Testing Recommendations

### Manual Testing Checklist

1. **Employee Registration**
   - [ ] Create employee with all required fields
   - [ ] Test duplicate employee number validation
   - [ ] Test duplicate email validation
   - [ ] Test duplicate PIN validation
   - [ ] Test photo upload (valid and invalid files)
   - [ ] Test required field validation
   - [ ] Verify QR code generation

2. **Employee List**
   - [ ] View paginated list
   - [ ] Test search functionality
   - [ ] Test filters (department, branch, status, type)
   - [ ] Test bulk activate
   - [ ] Test bulk deactivate
   - [ ] Test bulk archive
   - [ ] Test export to CSV

3. **Employee Profile**
   - [ ] View profile with all sections
   - [ ] Verify timeline entries
   - [ ] Test QR code print
   - [ ] Test QR code regenerate
   - [ ] Test activate/deactivate
   - [ ] Test archive

4. **Employee Edit**
   - [ ] Update all fields
   - [ ] Test photo replacement
   - [ ] Test PIN change
   - [ ] Verify timeline updates

5. **Status Management**
   - [ ] Change employment status
   - [ ] Test status business rules
   - [ ] Archive and restore employee

6. **Dashboard**
   - [ ] Verify employee statistics
   - [ ] Check recent hires count
   - [ ] Verify active/inactive counts

## Support

For issues or questions about the Employee Management Module:
1. Check the troubleshooting section above
2. Review the audit logs for error details
3. Check the application logs in `logs/` directory
4. Verify database migration was successful
5. Ensure all permissions are correctly assigned

## Version History

- **Phase 4.0** - Initial release with core employee management features
  - Employee registration with comprehensive fields
  - Employee list with search and filters
  - Employee profile with timeline
  - QR code generation (placeholder)
  - Status management
  - Bulk actions
  - CSV export
  - Audit trail integration
  - Dashboard statistics enhancement
