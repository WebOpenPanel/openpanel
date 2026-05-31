#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <shadow.h>
#include <crypt.h>
#include <unistd.h>

int main(int argc, char *argv[]) {
    if (argc != 3) {
        fprintf(stderr, "Usage: %s <username> <password>\n", argv[0]);
        return 2;
    }

    char *username = argv[1];
    char *password = argv[2];

    struct spwd *sp = getspnam(username);
    if (!sp || !sp->sp_pwdp) {
        return 1;
    }

    char *hash = sp->sp_pwdp;
    if (hash[0] == '!' || hash[0] == '*' || hash[0] == '\0') {
        return 1;
    }

    char *result = crypt(password, hash);
    if (result && strcmp(result, hash) == 0) {
        return 0;
    }

    return 1;
}
