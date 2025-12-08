# Build script for Docker deployment (Windows PowerShell)

Write-Host "🚀 Building Rice Mill Blockchain System..." -ForegroundColor Green
Write-Host ""

# Check if Docker is running
try {
    docker info | Out-Null
    Write-Host "✓ Docker is running" -ForegroundColor Green
} catch {
    Write-Host "❌ Error: Docker is not running. Please start Docker Desktop." -ForegroundColor Red
    exit 1
}
Write-Host ""

# Check if .env file exists
if (!(Test-Path .env)) {
    Write-Host "📝 Creating .env file from template..." -ForegroundColor Yellow
    Copy-Item .env.example .env
    Write-Host "⚠️  Please edit .env file and set your passwords!" -ForegroundColor Yellow
    Write-Host ""
}

Write-Host "🔨 Building containers..." -ForegroundColor Cyan
Write-Host ""

# Build and start containers
docker-compose -f docker-compose.prod.yml up --build -d

Write-Host ""
Write-Host "✅ Build complete!" -ForegroundColor Green
Write-Host ""
Write-Host "📊 Container Status:" -ForegroundColor Cyan
docker-compose -f docker-compose.prod.yml ps
Write-Host ""
Write-Host "🌐 Access your application:" -ForegroundColor Cyan
Write-Host "   Web Interface: http://localhost:8080" -ForegroundColor White
Write-Host "   Backend API:   http://localhost:3000" -ForegroundColor White
Write-Host "   MySQL:         localhost:3306" -ForegroundColor White
Write-Host "   CouchDB:       http://localhost:5984" -ForegroundColor White
Write-Host ""
Write-Host "📋 Useful commands:" -ForegroundColor Cyan
Write-Host "   View logs:     docker-compose -f docker-compose.prod.yml logs -f" -ForegroundColor White
Write-Host "   Stop all:      docker-compose -f docker-compose.prod.yml down" -ForegroundColor White
Write-Host "   Restart:       docker-compose -f docker-compose.prod.yml restart" -ForegroundColor White
Write-Host ""
