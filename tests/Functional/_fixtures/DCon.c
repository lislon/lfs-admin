#include <stdio.h>

/**
 * Compile with  /usr/bin/i686-w64-mingw32-gcc ./DCon.c -o ./DCon.exe && zip -r LfsDummyImage.zip DCon.exe
 */
int main()
{
  FILE *fp = fopen("deb.log", "w");
  if (fp == NULL) {
    printf("Failed to open deb.log for writing\n");
    return 1;
  }
  fprintf(fp, "Dummy server started\n");
  fclose(fp);
  return 0;
}
