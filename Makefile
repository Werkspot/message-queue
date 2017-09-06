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

coverage:
	./bin/generate_coverage

cs-fix:
	./bin/fix_code_standards

test:
	./bin/run_test_suite