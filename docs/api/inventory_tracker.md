# API: /inventory_tracker/

Requests that change data must send the logged-in user's id in an `X-User-Id` header. Add and update require a Manager or Employee; delete requires a Manager.

    No or unknown X-User-Id -> HTTP 401
    Role not allowed -> HTTP 403

## POST /api/inventory_tracker/add

JSON body:

*date* **required, YYYY-MM-DD** <br>
*total_value* **required, non-negative number**

    Post -> HTTP 201
    Not Post -> HTTP 405
    Invalid fields -> HTTP 400

## PUT /api/inventory_tracker/update/{inv_id}

JSON body: any of the fields from add. Only the fields sent are changed.

    Put -> HTTP 200
    Not Put -> HTTP 405
    Invalid id or fields / no fields sent -> HTTP 400
    Inventory snapshot not found -> HTTP 404

## DELETE /api/inventory_tracker/delete/{inv_id}

    Delete -> HTTP 200
    Not Delete -> HTTP 405
    Inventory snapshot not found -> HTTP 404
