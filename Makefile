all:
	git archive --prefix=fluidinfo/ -v --format tar HEAD | gzip > fluidinfo.tar.gz
