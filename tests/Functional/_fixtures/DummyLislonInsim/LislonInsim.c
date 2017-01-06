#include <stdio.h>
#include <unistd.h>

/**
 * Compile with  /usr/bin/i686-w64-mingw32-gcc ./DCon.c -o ./DCon.exe && zip -r LfsDummyImage.zip DCon.exe
 */
int main()
{
  printf("Dummy lislon insim running");
  sleep(300);
  return 0;
}
