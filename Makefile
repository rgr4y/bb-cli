.PHONY: all build install clean demo test hooks

all: build

build:
	php --define phar.readonly=0 create-phar.php

install: build
	cp bb ~/.local/bin/bb

demo: install
	vhs config/demo.tape
	git add -f demo.gif

test:
	php tests/phpunit.phar

hooks:
	cp .git-hooks/pre-commit .git/hooks/pre-commit
	cp .git-hooks/pre-push .git/hooks/pre-push
	chmod +x .git/hooks/pre-commit .git/hooks/pre-push
	@echo "Git hooks installed."

clean:
	rm -f bb bb.phar demo.gif
