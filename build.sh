#!/bin/bash
# Build script for Docker deployment

echo "🚀 Building Rice Mill Blockchain System..."
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Error: Docker is not running. Please start Docker Desktop."
    exit 1
fi

echo "✓ Docker is running"
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file from template..."
    cp .env.example .env
    echo "⚠️  Please edit .env file and set your passwords!"
    echo ""
fi

echo "🔨 Building containers..."
echo ""

# Build and start containers
docker-compose -f docker-compose.prod.yml up --build -d

echo ""
echo "✅ Build complete!"
echo ""
echo "📊 Container Status:"
docker-compose -f docker-compose.prod.yml ps
echo ""
echo "🌐 Access your application:"
echo "   Web Interface: http://localhost:8080"
echo "   Backend API:   http://localhost:3000"
echo "   MySQL:         localhost:3306"
echo "   CouchDB:       http://localhost:5984"
echo ""
echo "📋 Useful commands:"
echo "   View logs:     docker-compose -f docker-compose.prod.yml logs -f"
echo "   Stop all:      docker-compose -f docker-compose.prod.yml down"
echo "   Restart:       docker-compose -f docker-compose.prod.yml restart"
echo ""
