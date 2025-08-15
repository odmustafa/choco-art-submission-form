# Gallery Artist Submission System - Installation Guide

## Overview
This system provides a complete artist submission platform with:
- Public submission form with file uploads
- MySQL database storage with environment-based configuration
- Organized file system with individual submission folders
- Password-protected admin dashboard
- Submission status management
- **Secure environment variable configuration**

## Files Structure
```
your-domain.com/
├── index.html                          (Artist submission form)
├── process_submission.php              (Form processing backend)
├── admin.php                          (Admin dashboard)
├── config.php                         (Configuration loader)
├── .env                               (Environment variables - KEEP SECURE!)
├── .htaccess                          (Security rules)
├── submissions/                       (Auto-created submission folders)
│   ├── SUB_2024_abc123/
│   │   ├── artwork_1_timestamp.jpg
│   │   ├── artwork_2_timestamp.jpg
│   │   ├── submission_details.html
│   │   └── submission_data.json
│   └── SUB_2024_def456/
│       └── ...
```

## Installation Steps

### 1. Environment Configuration

1. **Create and configure the .env file**:
   ```bash
   # Copy the provided .env template
   cp .env.example .env
   
   # Edit with your actual credentials
   nano .env
   ```

2. **Update .env with your SiteGround credentials**:
   ```bash
   # Database Configuration
   DB_HOST=localhost
   DB_USERNAME=yoursiteground_username
   DB_PASSWORD=your_secure_password
   DB_NAME=yoursiteground_databasename
   
   # Security Settings
   ADMIN_SESSION_TIMEOUT=24
   MAX_FILE_SIZE=5242880
   MAX_FILES_PER_SUBMISSION=10
   
   # Email Configuration (Optional)
   SMTP_HOST=smtp.yourdomain.com
   SMTP_PORT=587
   SMTP_USERNAME=noreply@yourdomain.com
   SMTP_PASSWORD=your_email_password
   NOTIFICATION_EMAIL=gallery@yourdomain.com
   
   # Application Settings
   SITE_NAME=Your Gallery Name
   TIMEZONE=America/New_York
   DEBUG_MODE=false
   ```

### 2. Database Setup

1. **Create the database and tables** using the provided SQL schema
2. **Database credentials are now automatically loaded** from the .env file
3. **No need to edit PHP files** - all configuration is in .env

### 3. Security Setup

1. **Upload the .htaccess file** to protect sensitive files:
   - Prevents direct access to .env file
   - Protects config.php from external access
   - Adds security headers

2. **Set proper file permissions**:
   ```bash
   chmod 600 .env              # Read/write for owner only
   chmod 644 config.php        # Standard PHP file permissions
   chmod 644 *.php             # All PHP files
   chmod 755 submissions/      # Submissions folder writable
   chmod 644 .htaccess         # Web server configuration
   ```

### 4. SiteGround Specific Configuration

1. **File Manager Setup**:
   - Upload all files to your `public_html` directory
   - Ensure the `submissions` folder is writable
   - Verify .env file is uploaded and has correct permissions

2. **Environment Variables**:
   - All sensitive data is now in .env file
   - No database credentials in PHP source code
   - Easy to change without touching code

3. **Database Connection**:
   - System automatically loads credentials from .env
   - Better error handling for connection issues
   - Debug mode can be enabled in .env for troubleshooting

### 5. Admin Account Setup

1. **Default admin credentials**:
   - Username: `admin`
   - Password: `admin123`
   - **CRITICAL**: Change this immediately!

2. **Change admin password**:
   ```php
   <?php
   // Generate new password hash
   echo password_hash('your_new_secure_password', PASSWORD_DEFAULT);
   ?>
   ```
   
   Then update in database:
   ```sql
   UPDATE admin_users 
   SET password_hash = 'your_generated_hash_here' 
   WHERE username = 'admin';
   ```

### 6. Configuration Options

The .env file allows you to customize:

- **Database settings**: Host, credentials, database name
- **File upload limits**: Max file size and number of files
- **Session timeout**: How long admin sessions last
- **Email notifications**: SMTP settings for notifications
- **Site branding**: Site name displayed throughout
- **Debug mode**: Enable detailed error messages for troubleshooting
- **Timezone**: Set your local timezone

### 7. Email Notifications (Optional)

If you configure email settings in .env:
- Automatic notifications sent to gallery email when new submissions arrive
- Uses your SMTP settings or server mail() function
- Includes basic submission details

## Security Features

### Environment Variables:
- **No sensitive data in source code**
- Database credentials stored securely in .env
- .htaccess prevents web access to .env file
- Easy to change credentials without code modifications

### File Protection:
- .env file protected from web access
- config.php protected from direct access
- Submissions folder secured against PHP execution
- Backup files and logs hidden

### Session Security:
- Configurable session timeout
- Secure session token generation
- Database-stored sessions with expiration
- Automatic cleanup of expired sessions

## Usage Instructions

### For Artists (Public Form):
1. Visit `yourdomain.com/index.html`
2. Fill out all required fields
3. Upload artwork images (limits configured in .env)
4. Submit the form
5. Receive confirmation of successful submission

### For Admins (Dashboard):
1. Visit `yourdomain.com/admin.php`
2. Log in with admin credentials
3. View all submissions with filtering options
4. Click "View Details" to see full submission with images
5. Use "Update Status" to change submission status and add notes
6. Filter by status or search by artist/artwork details

## Troubleshooting

### Configuration Issues:

1. **Environment file not found**:
   - Verify .env file exists in root directory
   - Check file permissions (should be 600)
   - Ensure file is properly formatted

2. **Database connection errors**:
   - Verify credentials in .env file
   - Check if database exists
   - Test connection with phpMyAdmin

3. **Missing environment variables**:
   - System will show specific missing variables
   - All required variables must be set in .env
   - Use debug mode to see detailed error messages

### Debug Mode:
Set `DEBUG_MODE=true` in .env for detailed error messages during setup.

## Configuration Examples

### Production .env:
```bash
DB_HOST=localhost
DB_USERNAME=gallery_prod_user
DB_PASSWORD=super_secure_password_123
DB_NAME=gallery_submissions
ADMIN_SESSION_TIMEOUT=8
MAX_FILE_SIZE=10485760
SITE_NAME=Metropolitan Art Gallery
TIMEZONE=America/New_York
DEBUG_MODE=false
NOTIFICATION_EMAIL=submissions@gallery.com
```

### Development .env:
```bash
DB_HOST=localhost
DB_USERNAME=dev_user
DB_PASSWORD=dev_password
DB_NAME=gallery_dev
ADMIN_SESSION_TIMEOUT=72
MAX_FILE_SIZE=2097152
SITE_NAME=Gallery Dev Site
TIMEZONE=America/New_York
DEBUG_MODE=true
```

## Backup Recommendations

1. **Always backup .env file securely**:
   - Contains sensitive credentials
   - Store in secure, encrypted location
   - Never commit to version control

2. **Database Backup**:
   ```sql
   mysqldump -u username -p gallery_submissions > backup.sql
   ```

3. **Complete System Backup**:
   - Include all files except .env (backup separately)
   - Regular automated backups through SiteGround
   - Test restore procedures

## Migration and Updates

### Changing Credentials:
1. Update .env file with new credentials
2. No code changes required
3. System automatically uses new settings

### Moving Between Environments:
1. Copy all files except .env
2. Create new .env with environment-specific settings
3. Update database credentials as needed

## Security Best Practices

1. **Never expose .env file**:
   - Protected by .htaccess
   - Never commit to version control
   - Use different credentials for each environment

2. **Regular password updates**:
   - Change admin passwords regularly
   - Use strong, unique passwords
   - Consider two-factor authentication

3. **Monitor access**:
   - Review admin access logs
   - Monitor unusual submission patterns
   - Keep system updated

4. **File permissions**:
   - .env: 600 (owner read/write only)
   - PHP files: 644 (standard)
   - Directories: 755 (standard)

---

**Security Note**: The .env file contains sensitive information and should never be accessible via the web. The included .htaccess file provides this protection, but always verify that environment variables are not exposed through your web server configuration.

### 2. File Upload Setup

1. **Create submissions directory**:
   ```bash
   mkdir submissions
   chmod 755 submissions
   ```

2. **Set proper permissions**:
   - Submissions folder: `755` (read/write/execute for owner)
   - PHP files: `644` (read/write for owner, read for others)
   - HTML files: `644`

### 3. SiteGround Specific Configuration

1. **File Manager Setup**:
   - Upload all files to your `public_html` directory
   - Ensure the `submissions` folder is writable

2. **PHP Settings**:
   - Max file upload size: 5MB per file (adjustable in `php.ini`)
   - Max post size: 50MB (to allow multiple files)
   - Max execution time: 60 seconds

3. **Database Connection**:
   - Use SiteGround's MySQL hostname (usually `localhost`)
   - Database name format is usually: `username_dbname`

### 4. Security Configuration

1. **Change Default Admin Password**:
   - Default username: `admin`
   - Default password: `admin123`
   - **IMPORTANT**: Change this immediately after installation!
   
   ```sql
   UPDATE admin_users 
   SET password_hash = '$2y$10$your_new_password_hash_here' 
   WHERE username = 'admin';
   ```

2. **Generate New Password Hash**:
   ```php
   <?php
   echo password_hash('your_new_password', PASSWORD_DEFAULT);
   ?>
   ```

3. **Secure Submissions Folder**:
   Create `.htaccess` in submissions folder:
   ```apache
   # Prevent direct access to submission folders
   Options -Indexes
   
   # Allow access to images and HTML files only
   <FilesMatch "\.(jpg|jpeg|png|gif|webp|html)$">
       Order allow,deny
       Allow from all
   </FilesMatch>
   
   # Deny access to JSON files
   <FilesMatch "\.json$">
       Order deny,allow
       Deny from all
   </FilesMatch>
   ```

### 5. Email Configuration (Optional)

To send email notifications, add this to `process_submission.php` after successful submission:

```php
// Email notification
$to = "gallery@yourdomain.com";
$subject = "New Artist Submission - " . $submission_data['artwork_title'];
$message = "New submission received from " . $submission_data['first_name'] . " " . $submission_data['last_name'];
$headers = "From: noreply@yourdomain.com";
mail($to, $subject, $message, $headers);
```

## Usage Instructions

### For Artists (Public Form):
1. Visit `yourdomain.com/index.html`
2. Fill out all required fields
3. Upload artwork images (max 10 files, 5MB each)
4. Submit the form
5. Receive confirmation of successful submission

### For Admins (Dashboard):
1. Visit `yourdomain.com/admin.php`
2. Log in with admin credentials
3. View all submissions with filtering options
4. Click "View Details" to see full submission with images
5. Use "Update Status" to change submission status and add notes
6. Filter by status or search by artist/artwork details

## Troubleshooting

### Common Issues:

1. **File Upload Errors**:
   - Check PHP upload limits in `php.ini`
   - Verify submissions folder permissions (755)
   - Ensure disk space is available

2. **Database Connection Issues**:
   - Verify MySQL credentials
   - Check if database and tables exist
   - Ensure MySQL service is running

3. **Permission Errors**:
   - Set submissions folder to 755
   - PHP files should be 644
   - Check SiteGround file permissions in cPanel

4. **Image Display Issues**:
   - Verify image file paths in database
   - Check if files were uploaded successfully
   - Ensure proper file extensions

### Testing the System:

1. **Test Submission Form**:
   - Fill out form with test data
   - Upload sample images
   - Verify submission appears in admin dashboard
   - Check that files are created in submissions folder

2. **Test Admin Dashboard**:
   - Log in with admin credentials
   - Verify submissions display correctly
   - Test status updates
   - Check filtering and search functionality

## Customization Options

### Styling:
- Modify CSS in `index.html` for submission form appearance
- Update admin dashboard styles in `admin.php`
- Add your gallery branding and colors

### Form Fields:
- Add/remove fields in HTML form
- Update corresponding database columns
- Modify PHP processing logic

### File Types:
- Change accepted file types in JavaScript validation
- Update PHP file type checking
- Modify maximum file sizes

### Email Templates:
- Customize confirmation emails
- Add admin notification emails
- Include submission details in emails

## Backup Recommendations

1. **Database Backup**:
   ```sql
   mysqldump -u username -p gallery_submissions > backup.sql
   ```

2. **File Backup**:
   - Regularly backup the submissions folder
   - Keep copies of PHP configuration files

3. **Automated Backups**:
   - Set up SiteGround's automatic backup feature
   - Consider offsite backup solutions

## Support

For technical support:
- Check SiteGround's knowledge base for hosting-specific issues
- Review PHP error logs in cPanel
- Verify MySQL connection through phpMyAdmin
- Test file permissions using File Manager

## Security Best Practices

1. Keep admin passwords strong and change them regularly
2. Monitor submission folder for unusual activity
3. Regularly update PHP version through SiteGround
4. Review admin access logs periodically
5. Implement HTTPS for secure form submissions

---

**Note**: This system is designed for SiteGround hosting but can be adapted for other hosting providers with minimal modifications to the database connection settings and file paths.