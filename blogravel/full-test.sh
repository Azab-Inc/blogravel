#!/bin/bash
set -e

echo "========================================="
echo "  Blogravel Full Test Suite"
echo "========================================="

echo ""
echo "Layer 1-2: Pest Unit & Feature Tests"
echo "----------------------------------------"
docker compose exec laravel.test php artisan test --compact

echo ""
echo "Layer 3: Playwright E2E Tests"
echo "----------------------------------------"
PLAYWRIGHT_BROWSERS_PATH=~/.cache/ms-playwright npx playwright test --reporter=list

echo ""
echo "========================================="
echo "  All tests passed!"
echo "========================================="
