phpcheck:
	@dev-scripts/phpcheck.sh

phplint: phpcheck
	@dev-scripts/phplint.sh

phpunit:
	cd ../../tests/phpunit && php phpunit.php --group=GoogleLogin

tests: phplint phpunit

installhooks:
	ln -sf ${PWD}/dev-scripts/pre-commit .git/hooks/pre-commit
