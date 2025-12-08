# Files in Blockchain Folder - What to Keep/Delete

## ✅ KEEP These Files

### Essential PHP Files
- ✅ `db.php` - Database connection
- ✅ `blockchain_api.php` - Blockchain API helper
- ✅ `login.php`, `dashboard.php`, etc. - Main system files
- ✅ `blockchain.php` - View blockchain logs
- ✅ `verify_blockchain.php` - Verify hash chain

### Configuration Files
- ✅ `.htaccess` - Apache configuration
- ✅ `composer.json`, `composer.lock` - PHP dependencies

### Database Files
- ✅ `rice_mill_db (11).sql` - **KEEP THIS!**
  - Useful backup
  - Reference for database structure
  - Can re-import if needed
  - Small file (27KB)

### Documentation
- ✅ `README_BLOCKCHAIN.md` - Integration guide

## ❓ Optional Files (Review)

### Duplicate Files
- `buyingPalay (1).php` - Duplicate of `buyingPalay.php`?
  - Check if different
  - Keep if different, delete if duplicate

### Backup/Original Folders
- `original/` - Old versions?
  - Keep if you need reference
  - Delete if no longer needed
- `posvoid/` - Void transactions?
  - Keep if used
  - Review if needed

### Vendor Folder
- `vendor/` - Composer dependencies
  - ✅ **KEEP** - Required by PHP
  - Don't delete!

## ❌ Can Delete (If Not Needed)

### Documentation Folders
- `composer_installation guide/` - If already installed
  - Can delete after setup

### Large Files
- `bg.png` (2.4MB) - Background image
  - Keep if used in system
  - Delete if not used

## Summary

### ✅ MUST KEEP
- All PHP files (login.php, etc.)
- `db.php`
- `blockchain_api.php`
- `rice_mill_db (11).sql` ← **KEEP THIS!**
- `vendor/` folder
- Configuration files

### ❓ REVIEW
- Duplicate files (buyingPalay (1).php)
- Original/backup folders
- Large image files

### ❌ CAN DELETE
- Installation guides (after setup)
- Unused backup files

---

**Main Answer: `rice_mill_db (11).sql` - KEEP IT!** ✅

