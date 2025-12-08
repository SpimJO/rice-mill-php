# 🚀 QUICK REFERENCE - Rice Mill Blockchain Deployment

## 3 Ways to Run Your Application

### 1️⃣ LOCAL (XAMPP) - For Development
```powershell
# Start XAMPP (MySQL + Apache)
# Access: http://localhost/Blockchain

# Start backend (separate terminal)
cd E:\xampp\htdocs\Blockchain\backend
npm run dev
```
✅ **Fastest** | ✅ **No setup** | ✅ **Current method**

---

### 2️⃣ DOCKER (Local) - For Testing Deployment
```powershell
# First time only
.\build.ps1

# Or manually
docker-compose -f docker-compose.prod.yml up -d

# Access:
# Web: http://localhost:8080
# API: http://localhost:3000
```
✅ **Test production setup** | ✅ **All services included** | ⚠️ **Need Docker Desktop**

---

### 3️⃣ RENDER (Cloud) - For Production
**Prerequisites:**
- GitHub account
- Render.com account (free)
- Cloud MySQL database

**Steps:**
1. Setup cloud database → Import SQL
2. Push code to GitHub
3. Create services on Render
4. Set environment variables
5. Deploy! 🎉

✅ **Online access** | ✅ **Scalable** | ⚠️ **Need external database**

---

## 🗄️ Database Decision Tree

### Q: Saan mo gagamitin?

**Development/Testing locally?**
→ ✅ **XAMPP is perfect!**

**Hosting online (Render/Cloud)?**
→ ❌ **XAMPP won't work**
→ ✅ **Need cloud database:**
   - Render MySQL
   - PlanetScale (Free MySQL)
   - AWS RDS Free Tier
   - Railway

---

## 📁 Important Files Created

| File | Purpose |
|------|---------|
| `Dockerfile` | Web app container |
| `backend/Dockerfile` | Backend API container |
| `docker-compose.prod.yml` | Full stack orchestration |
| `config.php` | Smart DB config (local + cloud) |
| `build.ps1` | Windows build script |
| `DEPLOYMENT_GUIDE.md` | Complete guide (detailed) |
| `BUILD_AND_RUN.md` | Quick start (simple) |
| `PHP_MIGRATION_GUIDE.md` | Update PHP files guide |

---

## 🔧 Essential Commands

### Docker Commands
```powershell
# Start everything
docker-compose -f docker-compose.prod.yml up -d

# Stop everything
docker-compose -f docker-compose.prod.yml down

# View logs
docker-compose -f docker-compose.prod.yml logs -f

# Restart a service
docker-compose -f docker-compose.prod.yml restart web

# Check status
docker-compose -f docker-compose.prod.yml ps

# Clean everything (DANGER!)
docker-compose -f docker-compose.prod.yml down -v
```

### Database Commands
```powershell
# Export from XAMPP
cd C:\xampp\mysql\bin
.\mysqldump.exe -u root rice_mill_db > E:\backup.sql

# Import to cloud
mysql -h host.com -u user -p database < E:\backup.sql

# Test connection
mysql -h host.com -u user -p -e "SELECT 1;"
```

### Git Commands (for Render deployment)
```powershell
# Initialize
git init
git add .
git commit -m "Initial commit"

# Push to GitHub
git remote add origin https://github.com/yourusername/repo.git
git push -u origin main

# Update after changes
git add .
git commit -m "Update files"
git push
```

---

## 🎯 Migration Checklist

### Before Deploying to Production:

- [ ] **Update PHP files** to use `config.php`
  ```php
  // Change this:
  require_once 'db.php';
  
  // To this:
  require_once 'config.php';
  ```

- [ ] **Create `.env` file** from template
  ```powershell
  copy .env.example .env
  notepad .env  # Edit passwords
  ```

- [ ] **Test with Docker locally first**
  ```powershell
  .\build.ps1
  ```

- [ ] **Setup cloud database**
  - Create MySQL database
  - Import `rice_mill_db (11).sql`
  - Note connection details

- [ ] **Push to GitHub**
  ```powershell
  git init
  git add .
  git commit -m "Ready for deployment"
  git push
  ```

- [ ] **Deploy to Render**
  - Create backend service
  - Create web service
  - Set environment variables
  - Deploy!

---

## 🌐 Environment Variables

### For Local Development (.env)
```env
# Database
MYSQL_ROOT_PASSWORD=root123
MYSQL_DATABASE=rice_mill_db
MYSQL_USER=rice_mill_user
MYSQL_PASSWORD=rice_mill_pass

# Backend
BACKEND_API_URL=http://localhost:3000
```

### For Render (Web App)
```env
DB_HOST=mysql-host.render.com
DB_USER=rice_mill_user
DB_PASSWORD=your-secure-password
DB_NAME=rice_mill_db
DB_PORT=3306
BACKEND_API_URL=https://your-backend.onrender.com
```

### For Render (Backend API)
```env
PORT=3000
NODE_ENV=production
VERSION=v1
BASEROUTE=api
WHITELIST=https://your-web-app.onrender.com
ENC_KEY_SECRET=<generate-32-chars>
CIPHER_KEY_SECRET=<generate-32-chars>
API_KEY_SECRET=<generate-32-chars>
```

---

## 🆘 Common Issues & Quick Fixes

| Issue | Quick Fix |
|-------|----------|
| Docker not starting | Restart Docker Desktop |
| Port already in use | `taskkill /PID <PID> /F` |
| Database connection failed | Check credentials in `.env` |
| npm install failed | `npm cache clean --force` then retry |
| Cannot access localhost:8080 | Check if containers are running: `docker ps` |
| Build failed | Check logs: `docker-compose logs` |
| Permission denied | Run PowerShell as Administrator |

---

## 📊 Service URLs

### Local Development (XAMPP)
- Web: `http://localhost/Blockchain`
- Backend: `http://localhost:3000`
- MySQL: `localhost:3306`

### Docker Local
- Web: `http://localhost:8080`
- Backend: `http://localhost:3000`
- MySQL: `localhost:3306`
- CouchDB: `http://localhost:5984`

### Production (Render)
- Web: `https://your-app-name.onrender.com`
- Backend: `https://your-backend-name.onrender.com`
- MySQL: `your-db-host.render.com:3306`

---

## 💡 Pro Tips

1. **Always test locally first** before deploying
2. **Backup your database** before any migration
3. **Use environment variables** for sensitive data
4. **Check logs** when something goes wrong
5. **Start simple** (Web + DB) before adding blockchain
6. **Keep XAMPP setup** for quick local testing
7. **Document your cloud credentials** securely

---

## 🎓 Decision Matrix

### Should I use XAMPP or Cloud Database?

| Use Case | XAMPP | Cloud DB |
|----------|-------|----------|
| Local development | ✅ Yes | ❌ No |
| Testing Docker | ⚠️ Optional | ✅ Yes |
| Production hosting | ❌ No | ✅ Yes |
| Team collaboration | ❌ No | ✅ Yes |
| Internet access needed | ❌ No | ✅ Yes |
| Cost | ✅ Free | ⚠️ Paid* |

*Free tiers available

---

## 📱 Access URLs Summary

```
Local XAMPP:
→ http://localhost/Blockchain

Docker Local:
→ http://localhost:8080

Render Production:
→ https://your-custom-domain.onrender.com
```

---

## 🚀 Next Steps

1. ✅ **Installed** - All files created
2. ⏳ **Test Docker** - Run `.\build.ps1`
3. ⏳ **Migrate PHP** - Update to use `config.php`
4. ⏳ **Setup Cloud DB** - Choose provider
5. ⏳ **Deploy to Render** - Follow guide

---

**That's it! You're ready to deploy! 🎉**

For detailed instructions, see:
- `BUILD_AND_RUN.md` - Quick start
- `DEPLOYMENT_GUIDE.md` - Complete guide
- `PHP_MIGRATION_GUIDE.md` - Code updates
