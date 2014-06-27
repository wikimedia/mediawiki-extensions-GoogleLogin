phpcheck:
	@dev-scripts/phpcheck.sh

phplint: phpcheck
	@dev-scripts/phplint.sh

tests: phplint

installhooks:
	ln -sf ${PWD}/dev-scripts/pre-commit .git/hooks/pre-commit