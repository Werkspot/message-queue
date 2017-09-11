CURRENT_BRANCH="$(shell git rev-parse --abbrev-ref HEAD)"

default: help

help:
	@echo "Usage:"
	@echo "     make [command]"
	@echo "Available commands:"
	@grep '^[^#[:space:]].*:' Makefile | grep -v '^default' | grep -v '^_' | sed 's/://' | xargs -n 1 echo ' -'

install-dependencies:
	composer install

update-dependencies:
	composer update

cs-fix:
	./bin/fix_code_standards

test:
	./bin/fix_code_standards --dry-run
	./bin/run_test_suite

test_with_coverage:
	./bin/fix_code_standards --dry-run
	./bin/run_test_suite_with_coverage
