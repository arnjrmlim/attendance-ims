# Email Scheduling Setup Guide

This document explains how to set up automatic email scheduling for attendance reports in the Attendance Management System.

## Overview

The email scheduling feature allows you to automatically send attendance reports on a recurring schedule:
- **Every 15th of the month** - Sends on the 15th day of each month
- **End of month** - Sends on the last day of each month (handles 28, 29, 30, or 31 days correctly)
- **Both** - Sends on both the 15th and the last day of each month
- **Manual** - Disables automatic sending; emails must be sent manually

## Prerequisites

1. **Run the Phase 5 Migration**
   ```bash
   mysql -u root -p attendance_db < database/migrations/phase5.sql
   ```

2. **Configure SMTP Settings**
   - Navigate to `/email-settings` in your browser
   - Configure your SMTP server details
   - Click "Save Settings"
   - Test the configuration using the "Send Test Email" button

3. **Configure Email Schedule**
   - In the Email Settings page, select your preferred schedule from the "Schedule" dropdown
   - Select your timezone from the "Timezone" dropdown
   - Click "Save Settings"

## Setting Up the Cron Job

### Option 1: Using cPanel (Common for Shared Hosting)

1. Log in to your cPanel
2. Navigate to **Cron Jobs** under the **Advanced** section
3. Add a new cron job with the following settings:
   - **Minute**: `0`
   - **Hour**: `*` (every hour)
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: 
     ```bash
     curl -s "https://your-domain.com/email-schedule/check" > /dev/null 2>&1
     ```

### Option 2: Using Linux Crontab

1. SSH into your server
2. Edit the crontab:
   ```bash
   crontab -e
   ```
3. Add the following line:
   ```bash
   0 * * * * curl -s "https://your-domain.com/email-schedule/check" > /dev/null 2>&1
   ```
4. Save and exit

### Option 3: Using Windows Task Scheduler

1. Open **Task Scheduler**
2. Create a new task:
   - **Name**: AMS Email Schedule Check
   - **Trigger**: Monthly
     - **Start**: 7/15/2026 at 1:00:00 PM
     - **Months**: Select all months
     - **Days**: 15 and last day of month
   - **Action**: Start a program
     - **Program**: `C:\xampp\php\php.exe`
     - **Arguments**: `-r "file_get_contents('http://localhost/attendance-ims/attendance/public/email-schedule/check');"`
     - **Start in**: `C:\xampp\htdocs\attendance-ims\attendance`

3. Test the task by right-clicking and selecting "Run"
4. The system will send emails on the 15th and last day of each month at 1:00 PM

### Option 4: Using PHP CLI (Alternative)

If you prefer to run PHP directly instead of HTTP requests:

1. Create a CLI script at `public/cron-email-schedule.php`:
   ```php
   <?php
   require __DIR__ . '/../public/index.php';
   
   // Simulate a GET request to the email-schedule/check endpoint
   $_SERVER['REQUEST_METHOD'] = 'GET';
   $_SERVER['REQUEST_URI'] = '/email-schedule/check';
   
   // This will require authentication, so you may need to modify the controller
   // to allow CLI execution or pass authentication headers
   ```

2. Set up the cron job to run this script:
   ```bash
   0 * * * * php /path/to/attendance/public/cron-email-schedule.php
   ```

## Cron Schedule Explanation

The recommended cron schedule `0 * * * *` means:
- **0** - Run at minute 0
- ***** - Every hour
- ***** - Every day
- ***** - Every month
- ***** - Every day of the week

This runs the check every hour on the hour. The system will internally determine if today is a scheduled send date based on your configuration.

## Security Considerations

The `/email-schedule/check` endpoint requires administrator authentication. For cron jobs, you have two options:

### Option 1: Use Session-Based Authentication (Current Implementation)

If your cron job runs from the same server, you can:
1. Log in as administrator in a browser
2. Copy the session cookie
3. Include it in your curl request:
   ```bash
   curl -s "https://your-domain.com/email-schedule/check" -H "Cookie: session_id=your_session_id" > /dev/null 2>&1
   ```

### Option 2: Modify Controller for CLI/Token Authentication

For better security, modify `EmailScheduleController.php` to accept a token:

```php
public function check(): void
{
    // Allow CLI execution or token-based authentication
    $token = $_GET['token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    $validToken = $this->cfg->get('email_schedule_token', '');
    
    if (php_sapi_name() === 'cli' || ($validToken && $token === $validToken)) {
        // Allow execution
    } else {
        require_role(['administrator']);
    }
    
    $service = new EmailScheduleService();
    $result = $service->checkAndSendScheduled();
    $this->json($result, $result['success'] ? 200 : 500);
}
```

Then set a token in settings and use it in your cron job:
```bash
0 * * * * curl -s "https://your-domain.com/email-schedule/check?token=your_secure_token" > /dev/null 2>&1
```

## Monitoring and Troubleshooting

### Check Schedule Status

Visit `/email-schedule/status` (requires admin login) to see:
- Current schedule configuration
- Timezone setting
- Last sent date
- Next scheduled send dates
- Current date and day

### View Email Logs

Visit `/email-logs` to see:
- All email send attempts
- Success/failure status
- Error messages
- Retry counts

### View Audit Logs

Visit `/audit` and filter by module "email" to see:
- Scheduled email send attempts
- Success/failure status
- Send date and reason
- Timezone used

### Common Issues

**Issue**: Email not sending on scheduled date
- Check that `email_report_enabled` is set to "1" in settings
- Verify `email_report_recipient` is configured
- Check email logs for delivery errors
- Verify SMTP settings are correct

**Issue**: Email sent multiple times on the same day
- The system uses `last_email_sent_date` to prevent duplicates
- Check that this setting is being updated correctly
- Verify your cron job isn't running with excessive frequency

**Issue**: Wrong date used for scheduling
- Verify the timezone setting matches your local timezone
- The system uses the configured timezone to determine the current date

## Testing

To test the scheduling without waiting for the actual date:

1. Temporarily change the system date (not recommended for production)
2. Or modify the `EmailScheduleService::checkAndSendScheduled()` method to force a send
3. Or manually trigger the endpoint: `/email-schedule/check`

## API Endpoints

### GET /email-schedule/check
Checks if today is a scheduled send date and sends the email if so.
- **Authentication**: Administrator required (or token if configured)
- **Response**: JSON with success status and message

### GET /email-schedule/status
Returns the current schedule configuration and next send dates.
- **Authentication**: Administrator required
- **Response**: JSON with schedule details

## Database Settings

The following settings are stored in the `settings` table:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `email_schedule` | string | manual | Schedule: manual, 15th, end_of_month, both |
| `email_timezone` | string | UTC | Timezone for scheduling |
| `last_email_sent_date` | string | NULL | Last date email was sent (YYYY-MM-DD) |
| `email_report_enabled` | boolean | 0 | Whether automatic reports are enabled |
| `email_report_compress` | boolean | 0 | Whether to compress reports to ZIP |

## Support

For issues or questions:
1. Check the email logs for detailed error messages
2. Verify SMTP configuration with the test email feature
3. Review audit logs for scheduled send attempts
4. Ensure the cron job is running correctly
