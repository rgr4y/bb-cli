.PHONY: build install clean demo

build:
	php --define phar.readonly=0 create-phar.php

install: build
	cp bb ~/.local/bin/bb

demo: install
	vhs config/demo.tape
	git add -f demo.gif

clean:
	rm -f bb bb.phar demo.gif
