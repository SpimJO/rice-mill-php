# Pre-Deployment Checklist Script
# Run this before deploying to make sure everything is ready

Write-Host "🔍 Rice Mill Blockchain - Deployment Readiness Check" -ForegroundColor Cyan
Write-Host "=" * 60 -ForegroundColor Cyan
Write-Host ""

$allGood = $true

# Check 1: Docker Desktop
Write-Host "Checking Docker Desktop..." -ForegroundColor Yellow
try {
    docker --version | Out-Null
    Write-Host "✅ Docker is installed" -ForegroundColor Green
    
    try {
        docker info | Out-Null
        Write-Host "✅ Docker is running" -ForegroundColor Green
    } catch {
        Write-Host "⚠️  Docker is installed but not running" -ForegroundColor Yellow
        Write-Host "   → Start Docker Desktop" -ForegroundColor White
        $allGood = $false
    }
} catch {
    Write-Host "❌ Docker is not installed" -ForegroundColor Red
    Write-Host "   → Download from: https://www.docker.com/products/docker-desktop" -ForegroundColor White
    $allGood = $false
}
Write-Host ""

# Check 2: Required Files
Write-Host "Checking required files..." -ForegroundColor Yellow
$requiredFiles = @(
    "Dockerfile",
    "docker-compose.prod.yml",
    "config.php",
    ".env.example",
    "backend/Dockerfile",
    "backend/package.json"
)

foreach ($file in $requiredFiles) {
    if (Test-Path $file) {
        Write-Host "✅ $file exists" -ForegroundColor Green
    } else {
        Write-Host "❌ $file is missing" -ForegroundColor Red
        $allGood = $false
    }
}
Write-Host ""

# Check 3: Environment File
Write-Host "Checking environment configuration..." -ForegroundColor Yellow
if (Test-Path ".env") {
    Write-Host "✅ .env file exists" -ForegroundColor Green
    
    # Check if it has required variables
    $envContent = Get-Content .env -Raw
    if ($envContent -match "MYSQL_ROOT_PASSWORD" -and $envContent -notmatch "your_secure") {
        Write-Host "✅ .env file is configured" -ForegroundColor Green
    } else {
        Write-Host "⚠️  .env file needs configuration" -ForegroundColor Yellow
        Write-Host "   → Edit .env and set your passwords" -ForegroundColor White
        $allGood = $false
    }
} else {
    Write-Host "⚠️  .env file not found" -ForegroundColor Yellow
    Write-Host "   → Copy .env.example to .env and configure it" -ForegroundColor White
    Write-Host "   → Command: copy .env.example .env" -ForegroundColor White
    $allGood = $false
}
Write-Host ""

# Check 4: Backend Dependencies
Write-Host "Checking backend dependencies..." -ForegroundColor Yellow
if (Test-Path "backend/node_modules") {
    Write-Host "✅ Backend dependencies installed" -ForegroundColor Green
} else {
    Write-Host "⚠️  Backend dependencies not installed" -ForegroundColor Yellow
    Write-Host "   → Run: cd backend; npm install" -ForegroundColor White
    $allGood = $false
}
Write-Host ""

# Check 5: Database File
Write-Host "Checking database file..." -ForegroundColor Yellow
if (Test-Path "rice_mill_db (11).sql") {
    Write-Host "✅ Database SQL file exists" -ForegroundColor Green
} else {
    Write-Host "⚠️  Database SQL file not found" -ForegroundColor Yellow
    Write-Host "   → Export from XAMPP if needed" -ForegroundColor White
}
Write-Host ""

# Check 6: Ports Availability
Write-Host "Checking port availability..." -ForegroundColor Yellow
$ports = @(8080, 3000, 3306, 7050, 7051, 5984)
$portsInUse = @()

foreach ($port in $ports) {
    $connection = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue
    if ($connection) {
        $portsInUse += $port
    }
}

if ($portsInUse.Count -eq 0) {
    Write-Host "✅ All required ports are available" -ForegroundColor Green
} else {
    Write-Host "⚠️  Some ports are in use: $($portsInUse -join ', ')" -ForegroundColor Yellow
    Write-Host "   → Stop XAMPP or other services using these ports" -ForegroundColor White
    Write-Host "   → Or change ports in docker-compose.prod.yml" -ForegroundColor White
    $allGood = $false
}
Write-Host ""

# Check 7: Git (for Render deployment)
Write-Host "Checking Git (needed for Render deployment)..." -ForegroundColor Yellow
try {
    git --version | Out-Null
    Write-Host "✅ Git is installed" -ForegroundColor Green
    
    # Check if it's a git repository
    if (Test-Path ".git") {
        Write-Host "✅ Git repository initialized" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Not a Git repository yet" -ForegroundColor Yellow
        Write-Host "   → Run: git init" -ForegroundColor White
    }
} catch {
    Write-Host "⚠️  Git is not installed" -ForegroundColor Yellow
    Write-Host "   → Download from: https://git-scm.com/download/win" -ForegroundColor White
    Write-Host "   → Only needed for Render deployment" -ForegroundColor White
}
Write-Host ""

# Summary
Write-Host "=" * 60 -ForegroundColor Cyan
Write-Host "SUMMARY" -ForegroundColor Cyan
Write-Host "=" * 60 -ForegroundColor Cyan
Write-Host ""

if ($allGood) {
    Write-Host "🎉 Everything looks good! You're ready to deploy!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Cyan
    Write-Host "1. Test locally with Docker:" -ForegroundColor White
    Write-Host "   .\build.ps1" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "2. Deploy to Render:" -ForegroundColor White
    Write-Host "   → See DEPLOYMENT_GUIDE.md" -ForegroundColor Yellow
} else {
    Write-Host "⚠️  Some items need attention (see above)" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Fix the issues above, then:" -ForegroundColor Cyan
    Write-Host "1. Run this script again to verify" -ForegroundColor White
    Write-Host "2. Proceed with deployment" -ForegroundColor White
}
Write-Host ""

# Deployment Options
Write-Host "=" * 60 -ForegroundColor Cyan
Write-Host "DEPLOYMENT OPTIONS" -ForegroundColor Cyan
Write-Host "=" * 60 -ForegroundColor Cyan
Write-Host ""
Write-Host "Option 1: Local Development (Current)" -ForegroundColor Green
Write-Host "  → Use XAMPP (already working)" -ForegroundColor White
Write-Host ""
Write-Host "Option 2: Docker Local Test" -ForegroundColor Yellow
Write-Host "  → Run: .\build.ps1" -ForegroundColor White
Write-Host ""
Write-Host "Option 3: Deploy to Render" -ForegroundColor Cyan
Write-Host "  → See: DEPLOYMENT_GUIDE.md" -ForegroundColor White
Write-Host ""

Write-Host "=" * 60 -ForegroundColor Cyan
Write-Host "Press any key to exit..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
