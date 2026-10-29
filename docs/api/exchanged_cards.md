# API: /exchanged_cards/

Requests that change data must send the logged-in user's id in an `X-User-Id` header. Add and update require a Manager or Employee; delete requires a Manager.

    No or unknown X-User-Id -> HTTP 401
    Role not allowed -> HTTP 403

## POST /api/exchanged_cards/add

JSON body:

*trans_id* **required, must exist** <br>
*card_id* **required, must exist** <br>
*recieved* **required, true/false or 1/0**

    Post -> HTTP 201
    Not Post -> HTTP 405
    Invalid fields -> HTTP 400

## PUT /api/exchanged_cards/update/{exchange_id}

JSON body: any of the fields from add. Only the fields sent are changed.

    Put -> HTTP 200
    Not Put -> HTTP 405
    Invalid id or fields / no fields sent -> HTTP 400
    Exchanged card not found -> HTTP 404

## DELETE /api/exchanged_cards/delete/{exchange_id}

    Delete -> HTTP 200
    Not Delete -> HTTP 405
    Exchanged card not found -> HTTP 404
