# API: /cards/

Requests that change data must send the logged-in user's id in an `X-User-Id` header. Add and update require a Manager or Employee; delete requires a Manager.

    No or unknown X-User-Id -> HTTP 401
    Role not allowed -> HTTP 403

## GET /api/cards/search

Every parameter is optional. Each one you add narrows the results (they are combined with AND). Sending none returns every card.

*?name=* **partial match** (e.g. `chu` matches Pikachu) <br>
*?set=* <br>
*?set_num=* <br>
*?lang=* <br>
*?condition=* <br>
*?printing=* <br>
*?grade=* **exact match** <br>
*?price_date=* **YYYY-MM-DD** <br>
*?price_min=* <br>
*?price_max=* **price range, inclusive**

    Get -> HTTP 200
    Not Get -> HTTP 405
    Invalid price_date / price_min / price_max -> HTTP 400

## POST /api/cards/add

JSON body:

*name*, *set*, *set_num*, *lang* **required, max 45 characters** <br>
*price* **required, non-negative number** <br>
*price_date* **required, YYYY-MM-DD** <br>
*condition*, *printing*, *grade* **optional, max 45 characters**

    Post -> HTTP 201
    Not Post -> HTTP 405
    Invalid card fields -> HTTP 400

## PUT /api/cards/update/{card_id}

JSON body: any of the fields from add. Only the fields sent are changed. Send `null` to clear an optional field.

    Put -> HTTP 200
    Not Put -> HTTP 405
    Invalid id or fields / no fields sent -> HTTP 400
    Card not found -> HTTP 404

## DELETE /api/cards/delete/{card_id}

    Delete -> HTTP 200
    Not Delete -> HTTP 405
    Card not found -> HTTP 404
    Card is part of a transaction -> HTTP 409
