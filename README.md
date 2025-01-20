# Gamingrom API

This is the API for the gamingroom queue API.

## Install

1. Put the files somewhere on your server, outside the server root.

2. To get any use of this API, you'll want to create these two react apps:

    1. [gamingroom-admin](https://github.com/ornendil/gamingroom-admin)
    2. [gamingroom-display](https://github.com/ornendil/gamingroom-display)
    
    The generated content of `gamingroom-admin` should be in the public folder. The `gamingroom-display` can be wherever (I think).

3. Make your own secrey key in `jwt/secretkey.txt`. It can be anything, but make your own and keep it secret.

4. Make yourself a user (or several) in `users.json` and give it a password. Run `hash.php` in terminal to hash the password.

5. Set `$rootUrl` in `apiHeaders.php`

6. There's probably some hardcoded stuff you'll need to fix to get it to work for your project. For example, `api/sessions/index.php` has hardcoded which computer indices are allowed (line 34).

## How it works

API endpoints are in `public/api/`

