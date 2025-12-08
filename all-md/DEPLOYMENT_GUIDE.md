# Rice Mill Blockchain Deployment Guide

## 📋 Table of Contents
1. [Local Development Setup](#local-development-setup)
2. [Docker Setup](#docker-setup)
3. [Render.com Deployment](#rendercom-deployment)
4. [Database Migration](#database-migration)
5. [Troubleshooting](#troubleshooting)

---

## 🏠 Local Development Setup (XAMPP)

### Current Setup
Ang current setup mo ay gumagamit ng XAMPP para sa local development:
- **Database**: MySQL via XAMPP (localhost)
- **Web Server**: Apache via XAMPP
- **PHP Backend**: Via XAMPP
- **Node.js Backend**: Separate process (port 3000)

### Files to Use Locally
```php
// db.php - Local XAMPP connection
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "rice_mill_db";
?>
```

---

## 🐳 Docker Setup (Local Testing)

### Prerequisites
1. Install Docker Desktop for Windows
   - Download: https://www.docker.com/products/docker-desktop
   - After installation, restart computer

2. Enable WSL 2 (if needed)
   ```powershell
   wsl --install
   ```

### Build and Run with Docker

#### Option 1: Full Stack (with Hyperledger Fabric)
```powershell
# Navigate to project folder
cd E:\xampp\htdocs\Blockchain

# Create .env file
copy .env.example .env
# Edit .env and set your passwords

# Start all services
docker-compose -f docker-compose.prod.yml up -d

# View logs
docker-compose -f docker-compose.prod.yml logs -f

# Stop services
docker-compose -f docker-compose.prod.yml down
```

#### Option 2: Web + Database Only (Simple)
```powershell
# Build web container
docker build -t rice-mill-web .

# Build backend container
cd backend
docker build -t rice-mill-backend .
cd ..

# Run with docker-compose
docker-compose up -d web mysql backend
```

### Access Your Application
- **Web Interface**: http://localhost:8080
- **Backend API**: http://localhost:3000
- **MySQL**: localhost:3306
- **CouchDB**: http://localhost:5984 (admin/adminpw)

---

## 🚀 Render.com Deployment

### Sagot sa Tanong: Database Requirements
**❌ Hindi pwede ang XAMPP sa cloud deployment**
- XAMPP is for local development only
- Need mo ng external database service

**✅ Recommended Database Options:**
1. **Render PostgreSQL** (Free tier available)
2. **Render MySQL** (Paid, but reliable)
3. **PlanetScale** (Free MySQL-compatible)
4. **AWS RDS Free Tier**
5. **Railway** (Free tier with MySQL)

### Step-by-Step Render Deployment

#### Step 1: Prepare Database
1. Create MySQL database sa Render:
   - Go to https://dashboard.render.com
   - Click "New +" → "MySQL"
   - Note the connection details

2. Import your database:
   ```bash
   # Export from XAMPP
   mysqldump -u root rice_mill_db > rice_mill_db.sql
   
   # Import to Render (use their connection string)
   mysql -h <render-host> -u <user> -p <database> < rice_mill_db.sql
   ```

#### Step 2: Deploy Backend (Node.js)
1. Create new Web Service:
   - Repository: Your GitHub repo
   - Branch: main
   - Root Directory: `backend`
   - Build Command: `npm install && npm run build`
   - Start Command: `npm start`

2. Environment Variables:
   ```
   PORT=3000
   NODE_ENV=production
   VERSION=v1
   BASEROUTE=api
   WHITELIST=https://your-web-app.onrender.com
   ENC_KEY_SECRET=<generate-random-32-char>
   CIPHER_KEY_SECRET=<generate-random-32-char>
   API_KEY_SECRET=<generate-random-32-char>
   ```

#### Step 3: Deploy PHP Web App
1. Create new Web Service:
   - Use Docker
   - Dockerfile path: `./Dockerfile`

2. Environment Variables:
   ```
   DB_HOST=<your-render-mysql-host>
   DB_USER=<your-mysql-user>
   DB_PASSWORD=<your-mysql-password>
   DB_NAME=rice_mill_db
   DB_PORT=3306
   BACKEND_API_URL=<your-backend-url>
   ```

#### Step 4: Update PHP Files
Update `db.php` to use `config.php`:
```php
<?php
require_once 'config.php';
// Use $conn variable from config.php
?>
```

#### Step 5: Update Frontend API Calls
Update your PHP files na tumatawag sa backend:
```php
<?php
require_once 'config.php';

// Use $backendApiUrl variable
$response = file_get_contents($backendApiUrl . '/api/v1/blockchain/log');
?>
```

---

## 🗄️ Database Migration Guide

### From XAMPP to Cloud Database

#### Option 1: Using phpMyAdmin
1. Export from XAMPP:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Select `rice_mill_db`
   - Click "Export" → "Go"
   - Save the SQL file

2. Import to Cloud:
   - Use cloud provider's import tool
   - Or use command line (see below)

#### Option 2: Using Command Line
```powershell
# Export from XAMPP
cd C:\xampp\mysql\bin
.\mysqldump.exe -u root rice_mill_db > E:\backup.sql

# Import to Cloud (example with Render)
mysql -h your-host.render.com -u user -p database_name < E:\backup.sql
```

#### Option 3: Using Script
Create `migrate-db.php`:
```php
<?php
// Old database (XAMPP)
$old = new mysqli('localhost', 'root', '', 'rice_mill_db');

// New database (Cloud)
$new = new mysqli('host.render.com', 'user', 'pass', 'rice_mill_db');

// Get all tables
$tables = $old->query("SHOW TABLES");
while ($table = $tables->fetch_array()) {
    $tableName = $table[0];
    
    // Copy structure
    $structure = $old->query("SHOW CREATE TABLE $tableName")->fetch_array();
    $new->query($structure[1]);
    
    // Copy data
    $data = $old->query("SELECT * FROM $tableName");
    while ($row = $data->fetch_assoc()) {
        // Insert into new database
        // ... implementation ...
    }
}
?>
```

---

## 🔧 Configuration Files Summary

### For Local Development (XAMPP)
```
- Use db.php (original)
- Start XAMPP
- Run backend: cd backend && npm run dev
```

### For Docker (Local Testing)
```
- Use docker-compose.prod.yml
- Use config.php instead of db.php
- docker-compose up
```

### For Render.com (Production)
```
- Use Dockerfile
- Use config.php
- Set environment variables in Render dashboard
- Use external MySQL database
```

---

## 🎯 Quick Start Commands

### Docker Local Testing
```powershell
# First time setup
cd E:\xampp\htdocs\Blockchain
copy .env.example .env
notepad .env  # Edit passwords

# Start services
docker-compose -f docker-compose.prod.yml up -d

# Check status
docker-compose -f docker-compose.prod.yml ps

# View logs
docker-compose -f docker-compose.prod.yml logs -f web

# Stop services
docker-compose -f docker-compose.prod.yml down
```

### Backend Only (Without Hyperledger)
```powershell
# Start just web and database
docker-compose -f docker-compose.prod.yml up -d web mysql backend

# Skip Hyperledger services if not needed yet
```

---

## ❓ Troubleshooting

### Issue: Docker not starting
```powershell
# Restart Docker Desktop
# Or via PowerShell:
Restart-Service docker
```

### Issue: Port already in use
```powershell
# Check what's using the port
netstat -ano | findstr :8080

# Kill the process (replace PID)
taskkill /PID <PID> /F
```

### Issue: Database connection failed
1. Check if MySQL container is running:
   ```powershell
   docker ps | findstr mysql
   ```

2. Check connection inside container:
   ```powershell
   docker exec -it rice-mill-mysql mysql -u root -p
   ```

### Issue: Cannot access web interface
1. Check container logs:
   ```powershell
   docker logs rice-mill-web
   ```

2. Verify ports are mapped:
   ```powershell
   docker port rice-mill-web
   ```

---

## 📊 Architecture Overview

```
┌─────────────────────────────────────────┐
│          Render.com Cloud               │
├─────────────────────────────────────────┤
│                                         │
│  ┌──────────────┐  ┌─────────────────┐ │
│  │ PHP Web App  │  │ Node.js Backend │ │
│  │ (Apache)     │◄─┤ (Express API)   │ │
│  │ Port 80      │  │ Port 3000       │ │
│  └──────┬───────┘  └────────┬────────┘ │
│         │                   │          │
│         ▼                   ▼          │
│  ┌─────────────────────────────────┐  │
│  │   MySQL Database (Render)       │  │
│  │   rice_mill_db                  │  │
│  └─────────────────────────────────┘  │
│                                        │
└────────────────────────────────────────┘

Optional: Hyperledger Fabric (separate server)
```

---

## 🎓 Summary

### Tanong: Kailangan ba ng external database?
**OO, pag nag-host sa Render/Cloud:**
- ❌ XAMPP - Local lang, hindi accessible online
- ✅ Cloud MySQL - Accessible anywhere, scalable

### Tanong: Sapat ba ang XAMPP?
**Para sa development: OO**
- Perfect for local testing
- Free and easy to setup

**Para sa production: HINDI**
- Need cloud database
- Need proper hosting service
- Need SSL certificates

### Recommended Setup:
1. **Development**: XAMPP (current setup)
2. **Testing**: Docker local (para ma-test ang deployment)
3. **Production**: Render.com with cloud database

---

## 📝 Next Steps

1. ✅ Install Docker Desktop
2. ✅ Test locally with docker-compose
3. ✅ Create Render.com account
4. ✅ Setup cloud MySQL database
5. ✅ Deploy backend to Render
6. ✅ Deploy web app to Render
7. ✅ Update DNS (if custom domain)
8. ✅ Setup SSL (Render provides free SSL)

---

## 🆘 Need Help?

Common issues and solutions are in the Troubleshooting section above.

Para sa specific problems, check the logs:
```powershell
# Docker logs
docker-compose logs [service-name]

# Render logs
# Available in Render dashboard → Logs tab
```

---

**Good luck with your deployment! 🚀**
