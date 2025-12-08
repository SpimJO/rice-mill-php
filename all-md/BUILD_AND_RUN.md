# 🚀 Quick Build and Run Guide

## Para sa mga walang time magbasa ng mahaba! 😅

---

## Option 1: Local Development (XAMPP) - Current Setup

### Gawin mo ngayon:
```powershell
# 1. Start XAMPP (MySQL + Apache)
# 2. Open browser: http://localhost/Blockchain

# 3. Start backend (separate terminal)
cd E:\xampp\htdocs\Blockchain\backend
npm install
npm run dev
```

**That's it!** Gumana na yan. 

---

## Option 2: Docker Local Testing

### Pre-requisites:
1. Download at install Docker Desktop: https://www.docker.com/products/docker-desktop
2. Restart computer after installation

### Build at Run:
```powershell
# Navigate sa project
cd E:\xampp\htdocs\Blockchain

# Create environment file
copy .env.example .env

# Edit .env file (set passwords)
notepad .env

# Build at start lahat
docker-compose -f docker-compose.prod.yml up --build -d

# Check kung running
docker-compose -f docker-compose.prod.yml ps

# View logs
docker-compose -f docker-compose.prod.yml logs -f
```

### Access:
- Web: http://localhost:8080
- API: http://localhost:3000
- MySQL: localhost:3306

### Stop Docker:
```powershell
docker-compose -f docker-compose.prod.yml down
```

---

## Option 3: Deploy to Render.com

### Pre-requisites:
1. GitHub account
2. Render.com account (free)
3. Push your code to GitHub

### Steps:

#### A. Setup Database
1. Go to https://dashboard.render.com
2. Click "New +" → "MySQL"
3. Name: `rice-mill-db`
4. Click "Create Database"
5. Save the connection details

#### B. Import Database
```powershell
# Export from XAMPP
cd C:\xampp\mysql\bin
.\mysqldump.exe -u root rice_mill_db > E:\backup.sql

# Import to Render (use their connection details)
mysql -h <host-from-render> -u <user> -p <database> < E:\backup.sql
```

#### C. Deploy Backend
1. Render Dashboard → "New +" → "Web Service"
2. Connect your GitHub repo
3. Settings:
   - Name: `rice-mill-backend`
   - Environment: `Docker`
   - Dockerfile Path: `./backend/Dockerfile`
   - Branch: `main`
4. Add Environment Variables:
   ```
   PORT=3000
   NODE_ENV=production
   VERSION=v1
   BASEROUTE=api
   ENC_KEY_SECRET=<random-32-characters>
   CIPHER_KEY_SECRET=<random-32-characters>
   API_KEY_SECRET=<random-32-characters>
   WHITELIST=https://your-domain.onrender.com
   ```
5. Click "Create Web Service"

#### D. Deploy Web App
1. Render Dashboard → "New +" → "Web Service"
2. Connect same GitHub repo
3. Settings:
   - Name: `rice-mill-web`
   - Environment: `Docker`
   - Dockerfile Path: `./Dockerfile`
   - Branch: `main`
4. Add Environment Variables:
   ```
   DB_HOST=<from-your-mysql-service>
   DB_USER=<from-your-mysql-service>
   DB_PASSWORD=<from-your-mysql-service>
   DB_NAME=rice_mill_db
   DB_PORT=3306
   BACKEND_API_URL=https://rice-mill-backend.onrender.com
   ```
5. Click "Create Web Service"

#### E. Update Code for Production
Replace `db.php` imports with `config.php`:
```php
<?php
// OLD:
// require_once 'db.php';

// NEW:
require_once 'config.php';
// Now $conn will work both locally and on cloud!
?>
```

---

## 🎯 Quick Decision Tree

**Tanong: Saan mo gagamitin?**

### "Testing lang locally" → Option 1 (XAMPP)
- ✅ Fastest
- ✅ No setup needed
- ✅ Current setup mo

### "Test kung gumagana sa Docker" → Option 2 (Docker)
- ✅ Close to production
- ✅ All services in one command
- ⚠️ Need Docker Desktop

### "I-host online" → Option 3 (Render)
- ✅ Production ready
- ✅ Free tier available
- ⚠️ Need external database
- ⚠️ Need GitHub account

---

## 🔥 Common Build Errors and Fixes

### Error: "docker command not found"
**Fix**: Install Docker Desktop, restart computer

### Error: "Port already in use"
**Fix**: 
```powershell
# Find process using port
netstat -ano | findstr :8080

# Kill it (replace PID)
taskkill /PID <PID> /F
```

### Error: "Cannot connect to database"
**Fix**: Check `.env` file, make sure passwords are correct

### Error: "npm install failed"
**Fix**:
```powershell
cd backend
rm -rf node_modules
npm cache clean --force
npm install
```

### Error: "Permission denied" sa Docker
**Fix**: Run PowerShell as Administrator

---

## 📝 Checklist Before Building

### Local (XAMPP):
- [ ] XAMPP running
- [ ] MySQL started
- [ ] Apache started
- [ ] Backend dependencies installed (`npm install`)

### Docker:
- [ ] Docker Desktop installed
- [ ] `.env` file created
- [ ] Ports 8080, 3000, 3306 available

### Render:
- [ ] Code pushed to GitHub
- [ ] Database created on cloud
- [ ] Environment variables set
- [ ] Database imported

---

## 🎓 Para Hindi Ka Malito

### Database:
- **Local**: XAMPP MySQL (localhost)
- **Docker**: MySQL container
- **Render**: Cloud MySQL service

### Web Server:
- **Local**: XAMPP Apache
- **Docker**: Apache container
- **Render**: Render's web service

### Backend API:
- **Local**: `npm run dev` (port 3000)
- **Docker**: Node container (port 3000)
- **Render**: Render's web service

### Blockchain:
- **All environments**: Hyperledger Fabric (optional)
- **Can work without it**: System will use database fallback

---

## ✅ Success Indicators

### Local Development:
```
✓ XAMPP control panel shows MySQL and Apache running (green)
✓ Can access http://localhost/Blockchain
✓ Backend running on port 3000
✓ No errors in terminal
```

### Docker:
```
✓ docker-compose ps shows all containers "Up"
✓ Can access http://localhost:8080
✓ docker logs shows no errors
✓ Can connect to database
```

### Render:
```
✓ All services showing "Live" status
✓ Can access your domain (e.g., rice-mill-web.onrender.com)
✓ Backend responds to API calls
✓ No errors in Render logs
```

---

## 🆘 If All Else Fails

1. **Check logs**:
   ```powershell
   # Docker
   docker-compose logs -f
   
   # Local backend
   # Check terminal where npm run dev is running
   ```

2. **Restart everything**:
   ```powershell
   # Docker
   docker-compose down
   docker-compose up -d
   
   # XAMPP
   # Stop and start in control panel
   ```

3. **Clean install**:
   ```powershell
   # Backend
   cd backend
   rm -rf node_modules
   npm install
   
   # Docker
   docker-compose down -v
   docker-compose up --build
   ```

---

**Yan lang! Simple lang talaga. Choose your option and follow the steps. Good luck! 🚀**
