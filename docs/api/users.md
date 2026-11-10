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

## GET /api/users/list

Requires a Manager (send the `pokebase_session` cookie). Returns every user's `user_id`, `email`, `first_name`, `last_name` and `role`.

    Get -> HTTP 200
    Not Get -> HTTP 405
    No, expired, or logged-out session -> HTTP 401
    Role not allowed -> HTTP 403

## PUT /api/users/role/{user_id}

Requires a Manager (send the `pokebase_session` cookie).

JSON body:

*role* **required, one of user, employee, manager**

    Put -> HTTP 200
    Not Put -> HTTP 405
    Invalid id or role -> HTTP 400
    No, expired, or logged-out session -> HTTP 401
    Role not allowed -> HTTP 403
    User not found -> HTTP 404

## PUT /api/users/archive/{user_id}

Requires a Manager (send the `pokebase_session` cookie). Users are never deleted, archiving sets `users.archived` to 1. Archived users can't log in, their existing sessions stop working, and they are left out of `GET /api/users/list`.

JSON body:

*archived* **required, true/false or 1/0**

    Put -> HTTP 200
    Not Put -> HTTP 405
    Invalid id or archived value -> HTTP 400
    No, expired, or logged-out session -> HTTP 401
    Role not allowed -> HTTP 403
    User not found -> HTTP 404