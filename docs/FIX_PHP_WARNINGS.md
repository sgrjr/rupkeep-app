# Fix PHP Warnings on Remote Server

This guide helps resolve PHP startup warnings about duplicate module loading and missing extensions.

## Issues to Fix

1. **OPcache loaded twice** - "Cannot load Zend OPcache - it was already loaded"
2. **XSL extension missing/broken** - "Unable to load dynamic library 'xsl'"
3. **Modules loaded twice** - curl, fileinfo, ftp, gd, mbstring, zip

## Step-by-Step Fix

### Step 1: Find Your PHP Configuration

```bash
# Find PHP version and config location
php -v
php --ini
```

This will show:
- Main php.ini file location (usually `/etc/php/8.x/cli/php.ini` or `/etc/php/8.x/fpm/php.ini`)
- Additional .ini files directory (usually `/etc/php/8.x/cli/conf.d/` or `/etc/php/8.x/fpm/conf.d/`)

**Note:** You may have separate configs for:
- CLI (command line): `/etc/php/8.x/cli/`
- FPM (web server): `/etc/php/8.x/fpm/`
- Both need to be fixed if warnings appear in both contexts

### Step 2: Check What's Loading Extensions

```bash
# Find your PHP version (e.g., 8.2)
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

# Check CLI config directory
ls -la /etc/php/${PHP_VERSION}/cli/conf.d/

# Check FPM config directory (if using FPM)
ls -la /etc/php/${PHP_VERSION}/fpm/conf.d/

# Check main php.ini files
cat /etc/php/${PHP_VERSION}/cli/php.ini | grep -E "^extension=|^zend_extension="
cat /etc/php/${PHP_VERSION}/fpm/php.ini | grep -E "^extension=|^zend_extension=" 2>/dev/null
```

### Step 3: Fix OPcache Duplicate Load

OPcache is usually loaded as `zend_extension`, not `extension`. Check:

```bash
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

# Check for OPcache in main php.ini
grep -n "opcache" /etc/php/${PHP_VERSION}/cli/php.ini

# Check for OPcache .ini files
ls -la /etc/php/${PHP_VERSION}/cli/conf.d/*opcache* 2>/dev/null

# Check what's loading it
grep -r "zend_extension.*opcache" /etc/php/${PHP_VERSION}/cli/
```

**Fix:** OPcache should only be loaded once. Usually it's loaded in a `.ini` file in `conf.d/`. If you see it in both `php.ini` AND a `.ini` file, comment it out in one place:

```bash
# Edit main php.ini
sudo nano /etc/php/${PHP_VERSION}/cli/php.ini

# Find and comment out (add ; at start of line):
; zend_extension=opcache
# (Keep it enabled in the conf.d file instead)
```

### Step 4: Fix XSL Extension (Remove if Not Needed)

XSL extension requires libxml2 and is often not needed. If you don't use XSLT, disable it:

```bash
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

# Check if xsl is loaded
grep -r "extension.*xsl" /etc/php/${PHP_VERSION}/cli/conf.d/

# Disable it by renaming the .ini file (add .disabled extension)
sudo mv /etc/php/${PHP_VERSION}/cli/conf.d/20-xsl.ini /etc/php/${PHP_VERSION}/cli/conf.d/20-xsl.ini.disabled 2>/dev/null || true

# If it's in FPM config, disable it there too
sudo mv /etc/php/${PHP_VERSION}/fpm/conf.d/20-xsl.ini /etc/php/${PHP_VERSION}/fpm/conf.d/20-xsl.ini.disabled 2>/dev/null || true
```

**If you DO need XSL**, install it properly:
```bash
# Install XSL extension
sudo apt-get install php-xsl

# Or for specific PHP version
sudo apt-get install php8.2-xsl  # Replace 8.2 with your PHP version
```

### Step 5: Fix Duplicate Module Loading

The warnings about curl, fileinfo, ftp, gd, mbstring, zip being loaded twice mean they're in both:
- Main `php.ini` file
- AND separate `.ini` files in `conf.d/`

**Fix:** Extensions should only be loaded in ONE place. Usually it's best to load them in `conf.d/` files (separate .ini files), not in main php.ini.

```bash
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

# Check main php.ini for extension lines
grep -n "^extension=" /etc/php/${PHP_VERSION}/cli/php.ini

# Check conf.d for extension files
ls -la /etc/php/${PHP_VERSION}/cli/conf.d/ | grep -E "curl|fileinfo|ftp|gd|mbstring|zip"
```

**Option 1: Comment out extensions in main php.ini (Recommended)**

```bash
# Edit main php.ini
sudo nano /etc/php/${PHP_VERSION}/cli/php.ini

# Find and comment out these lines (add ; at start):
; extension=curl
; extension=fileinfo
; extension=ftp
; extension=gd
; extension=mbstring
; extension=zip

# Save and exit (Ctrl+X, Y, Enter)
```

**Option 2: Remove duplicate .ini files from conf.d (if extensions are in php.ini)**

This is less common, but if extensions are already in php.ini, you could remove the .ini files. However, Option 1 is safer.

### Step 6: Apply Same Fixes to FPM Config (if using PHP-FPM)

If you're using PHP-FPM for your web server, apply the same fixes to the FPM config:

```bash
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

# Disable xsl in FPM
sudo mv /etc/php/${PHP_VERSION}/fpm/conf.d/20-xsl.ini /etc/php/${PHP_VERSION}/fpm/conf.d/20-xsl.ini.disabled 2>/dev/null || true

# Edit FPM php.ini to comment out duplicate extensions
sudo nano /etc/php/${PHP_VERSION}/fpm/php.ini

# Comment out the same extension lines
; extension=curl
; extension=fileinfo
; extension=ftp
; extension=gd
; extension=mbstring
; extension=zip
```

### Step 7: Verify the Fixes

```bash
# Test CLI PHP (should have no warnings)
php -v

# Test a PHP command
php artisan --version

# Check loaded modules (should list without warnings)
php -m | head -20

# If using FPM, restart it
sudo systemctl restart php-fpm
# or
sudo systemctl restart php8.2-fpm  # Replace with your PHP version

# Restart web server
sudo systemctl restart nginx
# or
sudo systemctl restart apache2
```

### Step 8: Quick Verification Script

Run this to check for remaining issues:

```bash
php -r "echo 'PHP Version: ' . PHP_VERSION . PHP_EOL;"
php -m > /tmp/php_modules.txt 2>&1
if grep -q "Warning" /tmp/php_modules.txt; then
    echo "Warnings still present:"
    grep "Warning" /tmp/php_modules.txt
else
    echo "✓ No warnings detected!"
fi
```

## Summary of Changes Needed

1. **Comment out duplicate extensions** in main php.ini (curl, fileinfo, ftp, gd, mbstring, zip)
2. **Disable or fix xsl extension** (remove if not needed)
3. **Ensure OPcache is only loaded once** (usually in conf.d file)
4. **Apply same fixes to FPM config** if using PHP-FPM

## Common Locations

- **Debian/Ubuntu CLI**: `/etc/php/8.2/cli/php.ini` and `/etc/php/8.2/cli/conf.d/`
- **Debian/Ubuntu FPM**: `/etc/php/8.2/fpm/php.ini` and `/etc/php/8.2/fpm/conf.d/`
- **CentOS/RHEL**: Usually `/etc/php.ini` and `/etc/php.d/`

Replace `8.2` with your actual PHP version.
