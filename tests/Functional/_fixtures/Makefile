CC=i686-w64-mingw32-gcc
CFLAGS=-Wall -s
TGT=obj
all: dummymake

dummymake: $(TGT)
	$(CC) $(CFLAGS) ./DCon.c -o $(TGT)/DCon.exe

$(TGT):
		mkdir -p $(TGT)

clean:
		rm -rf $(TGT)
