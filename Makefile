all:
	git archive --prefix=fluidinfo/ -v --format tar HEAD | gzip > fluidinfo.tar.gz

clean:
	rm -f fluidinfo.tar.gz
	find . -name '*~' -print0 | xargs -0 -r rm
