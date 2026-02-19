.PHONY: build install clean demo test

build:
	php --define phar.readonly=0 create-phar.php

install: build
	cp bb ~/.local/bin/bb

demo: install
	vhs config/demo.tape
	git add -f demo.gif

test:
	php tests/phpunit.phar

clean:
	rm -f bb bb.phar demo.gif
