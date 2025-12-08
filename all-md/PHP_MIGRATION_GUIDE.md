# Migration Checklist: Update PHP Files for Production

## 🎯 Goal
Replace `db.php` with `config.php` para gumana both sa local (XAMPP) and production (Render).

---

## ✅ Step-by-Step Migration

### Step 1: Backup Current Files
```powershell
# Create backup folder
mkdir backup
Copy-Item *.php backup/
```

### Step 2: Update Database Connection

**Find this pattern in your PHP files:**
```php
<?php
require_once 'db.php';
// or
include 'db.php';
```

**Replace with:**
```php
<?php
require_once 'config.php';
// or
include 'config.php';
```

### Step 3: Files to Update

Gawin mo ito sa mga files:
- [ ] `blockchain.php`
- [ ] `blockchain_api.php`
- [ ] `buyingPalay.php`
- [ ] `dashboard.php`
- [ ] `get_total_palay.php`
- [ ] `login.php`
- [ ] `milling.php`
- [ ] `pos.php`
- [ ] `print_pv_composer.php`
- [ ] `print_receipt.php`
- [ ] `products.php`
- [ ] `record_void.php`
- [ ] `reports.php`
- [ ] `snapshot_inventory.php`
- [ ] `test-api-key.php`
- [ ] `test-blockchain-log.php`
- [ ] `usermanagement.php`
- [ ] `verify_admin.php`
- [ ] `verify_blockchain.php`

---

## 🔍 Example: Update buyingPalay.php

### BEFORE:
```php
<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Rest of code...
?>
```

### AFTER:
```php
<?php
session_start();
require_once 'config.php';  // ← Changed this line only!

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Rest of code...
?>
```

---

## 🛠️ Automated Find & Replace

### Using PowerShell:
```powershell
# Find all files using db.php
Get-ChildItem -Filter *.php | Select-String -Pattern "db\.php"

# Replace in all PHP files (dry run first)
Get-ChildItem -Filter *.php | ForEach-Object {
    $content = Get-Content $_.FullName
    $content -replace "require_once 'db\.php';", "require_once 'config.php';"
    # Remove the comment to actually write:
    # | Set-Content $_.FullName
}
```

### Manual Method (Safer):
1. Open each PHP file in VS Code
2. Press `Ctrl + H` (Find and Replace)
3. Find: `db.php`
4. Replace: `config.php`
5. Click "Replace All" in current file
6. Save file

---

## 🧪 Testing

### Test Locally (XAMPP):
1. Update ONE file first (e.g., `login.php`)
2. Test if login still works
3. If OK, update rest of files

### Test with config.php:
```php
<?php
require_once 'config.php';

// Test connection
if ($conn->ping()) {
    echo "✅ Database connection OK!";
} else {
    echo "❌ Database connection failed!";
}

// Test backend URL
echo "<br>Backend URL: " . $backendApiUrl;
?>
```

---

## 📝 What config.php Does

**Automatic Environment Detection:**
```php
// Local (XAMPP)
if (no environment variables) {
    $host = "localhost";
    $user = "root";
    $pass = "";
}

// Production (Render/Docker)
if (environment variables exist) {
    $host = getenv('DB_HOST');      // mysql.render.com
    $user = getenv('DB_USER');      // rice_mill_user
    $pass = getenv('DB_PASSWORD');  // your-password
}
```

**Benefits:**
- ✅ No code changes needed between local/production
- ✅ Secure (passwords in environment, not code)
- ✅ Works with both XAMPP and cloud databases
- ✅ Easy to maintain

---

## 🚨 Important Notes

### DO NOT Delete `db.php`!
Keep it as backup. Pwede mo pa rin gamitin locally if needed.

### Test One File First
Before updating all files:
1. Update `login.php` only
2. Test login functionality
3. If working, proceed with others

### Backend API Calls
If your PHP files call the backend API, update to use:
```php
<?php
require_once 'config.php';

// Use $backendApiUrl variable
$apiUrl = $backendApiUrl . '/api/v1/blockchain/log';
$response = file_get_contents($apiUrl);
?>
```

---

## ✅ Verification Checklist

After updating all files:

- [ ] All PHP files use `config.php` instead of `db.php`
- [ ] Tested login locally
- [ ] Tested database operations
- [ ] No PHP errors in browser
- [ ] Backend API calls work
- [ ] Ready for Docker testing
- [ ] Ready for production deployment

---

## 🆘 Troubleshooting

### Issue: "config.php not found"
**Fix:** Make sure you created `config.php` in the root folder

### Issue: "Database connection failed"
**Fix:** Check if:
- XAMPP MySQL is running (local)
- Environment variables are set (production)
- Database credentials are correct

### Issue: "Call to undefined function getenv()"
**Fix:** This shouldn't happen. But if it does, check PHP version (need 5.3+)

### Issue: "Backend API not responding"
**Fix:**
- Local: Check if `npm run dev` is running
- Production: Check Render service status

---

**Done! After this migration, your app will work seamlessly both locally and in production! 🚀**
