CC=i686-w64-mingw32-gcc
CFLAGS=-Wall -s
TGT=obj
NAME=LislonInsim

all: pack clean

$(NAME): $(TGT)
	$(CC) $(CFLAGS) ./$(NAME).c -o $(TGT)/$(NAME).exe

$(TGT):
		mkdir -p $(TGT)

pack: $(NAME)
		zip -r $(NAME).zip $(TGT)/$(NAME).exe

clean:
		rm -rf $(TGT)
