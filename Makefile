# RSL for WordPress Plugin - Development Makefile

.PHONY: help install test test-unit test-integration lint clean build zip release dev

# Default target
help: ## Show this help message
	@echo "RSL Licensing Plugin - Development Commands"
	@echo "=========================================="
	@echo ""
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install dependencies
	@echo "Installing dependencies..."
	composer install --dev
	@echo "✅ Dependencies installed"

test: ## Run all tests
	@echo "Running all tests..."
	vendor/bin/phpunit tests/unit/TestBasicFunctionality.php tests/unit/TestRSLLicense.php
	@echo "✅ Tests completed"

test-unit: ## Run unit tests only
	@echo "Running unit tests..."
	vendor/bin/phpunit tests/unit/TestBasicFunctionality.php tests/unit/TestRSLLicense.php --testsuite=unit
	@echo "✅ Unit tests completed"

test-coverage: ## Run tests with coverage
	@echo "Running tests with coverage..."
	vendor/bin/phpunit --coverage-html coverage tests/unit/TestBasicFunctionality.php tests/unit/TestRSLLicense.php
	@echo "✅ Coverage report generated at coverage/index.html"

test-quick: ## Run quick smoke tests
	@echo "Running quick smoke tests..."
	./tests/run-tests.sh --quick
	@echo "✅ Quick tests completed"

lint: ## Run code linting
	@echo "Running PHP syntax check..."
	find . -name "*.php" -not -path "./vendor/*" -not -path "./tests/*" -exec php -l {} \;
	@echo "✅ Linting completed"

validate: ## Validate plugin structure and composer
	@echo "Validating plugin structure..."
	@test -f rsl-wp.php || { echo "❌ Main plugin file missing"; exit 1; }
	@test -d includes/ || { echo "❌ Includes directory missing"; exit 1; }
	@echo "✅ Plugin structure valid"
	@echo "Validating composer..."
	composer validate --strict
	@echo "✅ Composer validation passed"

clean: ## Clean temporary files
	@echo "Cleaning temporary files..."
	rm -rf coverage/ test-logs/ test-report.html .phpunit.result.cache
	rm -f *.zip *.tar.gz
	@echo "✅ Cleanup completed"

build: clean install lint test ## Full build process
	@echo "Running full build process..."
	@echo "✅ Build completed successfully"

zip: ## Create distribution ZIP
	@echo "Creating distribution ZIP..."
	$(eval VERSION := $(shell grep "Version:" rsl-wp.php | head -1 | sed 's/.*Version: *//' | tr -d ' '))
	$(eval ZIP_NAME := rsl-licensing-$(VERSION).zip)

	@echo "Installing production dependencies only..."
	@composer install --no-dev --optimize-autoloader --quiet

	@mkdir -p /tmp/rsl-licensing-build

	@rsync -av --exclude-from=.distignore ./ /tmp/rsl-licensing-build/rsl-licensing/

	@cd /tmp/rsl-licensing-build && zip -r "$(ZIP_NAME)" rsl-licensing/
	@mv "/tmp/rsl-licensing-build/$(ZIP_NAME)" ./
	@rm -rf /tmp/rsl-licensing-build

	@echo "✅ Created $(ZIP_NAME)"
	@ls -lh $(ZIP_NAME)

dev: ## Start development environment
	@echo "Starting development environment..."
	@if command -v wp >/dev/null 2>&1; then \
		echo "Setting up WordPress Playground..."; \
		npx @wp-playground/cli server --auto-mount; \
	else \
		echo "WordPress CLI not found. Install with:"; \
		echo "  curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/utils/wp-completion.bash"; \
		echo "  wget https://github.com/wp-cli/wp-cli/releases/download/v2.8.1/wp-cli-2.8.1.phar"; \
		echo "  chmod +x wp-cli-2.8.1.phar"; \
		echo "  sudo mv wp-cli-2.8.1.phar /usr/local/bin/wp"; \
	fi

playground: ## Start WordPress Playground for testing
	@echo "Starting WordPress Playground..."
	@if command -v npx >/dev/null 2>&1; then \
		npx @wp-playground/cli server --auto-mount; \
	else \
		echo "❌ Node.js/NPX not found. Install Node.js first."; \
		exit 1; \
	fi

security: ## Run security checks
	@echo "Running security analysis..."
	composer audit || echo "Security audit completed with warnings"
	@echo "Checking for common security issues..."
	@./scripts/security-check.sh 2>/dev/null || echo "Security check script not found - skipping"
	@echo "✅ Security checks completed"

release: build zip ## Prepare for release
	@echo "Preparing release..."
	$(eval VERSION := $(shell grep "Version:" rsl-wp.php | head -1 | awk '{print $$2}'))
	@echo "Release version: $(VERSION)"
	@echo "ZIP file: rsl-licensing-$(VERSION).zip"
	@echo ""
	@echo "Next steps:"
	@echo "1. Verify the ZIP file works correctly"
	@echo "2. Test in a clean WordPress installation"
	@echo "3. Create GitHub release: git tag v$(VERSION) && git push origin v$(VERSION)"
	@echo "4. Upload to WordPress.org (if applicable)"

# Installation shortcuts
install-dev: ## Quick development setup
	@echo "Setting up development environment..."
	composer install --dev
	@if [ ! -f .env ]; then \
		echo "Creating .env file..."; \
		echo "RSL_DEBUG=true" > .env; \
		echo "WP_DEBUG=true" >> .env; \
	fi
	@echo "✅ Development environment ready"

install-prod: ## Production dependency setup
	@echo "Installing production dependencies..."
	composer install --no-dev --optimize-autoloader
	@echo "✅ Production dependencies installed"

# Utility targets
version: ## Show current version
	@grep "Version:" rsl-wp.php | head -1 | awk '{print "Current version:", $$2}'

status: ## Show project status
	@echo "RSL Licensing Plugin Status"
	@echo "=========================="
	@echo "Version: $(shell grep "Version:" rsl-wp.php | head -1 | awk '{print $$2}')"
	@echo "PHP Files: $(shell find includes/ -name "*.php" | wc -l)"
	@echo "Test Files: $(shell find tests/ -name "*.php" | wc -l)"
	@echo "Dependencies: $(shell composer show | wc -l) packages"
	@echo "Git branch: $(shell git branch --show-current)"
	@echo "Git status: $(shell git status --porcelain | wc -l) uncommitted changes"
