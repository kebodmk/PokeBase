# API: /users/

## POST /api/users/register

*?email=* **email address** <br>
*?password=*  **password** <br>
*?first_name=* **first name** <br>
*?last_name=*  **last name**

    Post -> HTTP 201
    Not Post -> HTTP 405
    User already exists -> HTTP 409
    Invalid user registration credentials -> HTTP 400

## POST /api/users/login

On success, starts a session that lasts 8 hours and sets it in an `HttpOnly`, `SameSite=Strict` cookie named `pokebase_session`. The token is never in the response body. The database keeps only a SHA-256 hash of it (`api_sessions.token_hash`).

    Post -> HTTP 200
    Not Post -> HTTP 405
    Invalid login credentials -> HTTP 401

## POST /api/users/logout

Deletes the session in the `pokebase_session` cookie from `api_sessions` and clears the cookie.


    Post -> HTTP 200
    Not Post -> HTTP 405