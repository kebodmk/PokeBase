# API: /audits/

Requests that change data must send the logged-in user's id in an `X-User-Id` header. Add and update require a Manager or Employee; delete requires a Manager.

    No or unknown X-User-Id -> HTTP 401
    Role not allowed -> HTTP 403

## POST /api/audits/add

JSON body:

*user_id* **required, must exist** <br>
*date* **required, YYYY-MM-DD** <br>
*notes* **required, max 45 characters**

    Post -> HTTP 201
    Not Post -> HTTP 405
    Invalid fields -> HTTP 400

## PUT /api/audits/update/{audit_id}

JSON body: any of the fields from add. Only the fields sent are changed.

    Put -> HTTP 200
    Not Put -> HTTP 405
    Invalid id or fields / no fields sent -> HTTP 400
    Audit not found -> HTTP 404

## DELETE /api/audits/delete/{audit_id}

    Delete -> HTTP 200
    Not Delete -> HTTP 405
    Audit not found -> HTTP 404
