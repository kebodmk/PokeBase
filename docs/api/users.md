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
    Post -> HTTP 200
    Not Post -> HTTP 405
    Invalid login credentials -> HTTP 401

## POST /api/users/logout
    Post -> HTTP 200
    Not Post -> HTTP 405