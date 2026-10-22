# API: /transactions/

## POST /api/transactions/add

Adds the transaction row, then an `exchanged_cards` row for each entry in `cards`.
Each card entry either points at an existing card with `card_id`, or includes the full card information, in which case the card is created first.
All input is validated before anything is written. If a database error happens partway through, the rows this request already inserted are removed.

*date* **required, YYYY-MM-DD** <br>
*user_id* **required, must exist** <br>
*sold_for* **optional, non-negative number or null** <br>
*cards* **optional, list of card entries**

Card entry:

*recieved* **required, true if the card came in, false if it went out** <br>
*card_id* **an existing card**, or the card fields from `POST /api/cards/add`

```json
{
  "date": "2026-10-22",
  "user_id": 1,
  "sold_for": 20.00,
  "cards": [
    { "card_id": 12, "recieved": false },
    { "recieved": true, "name": "Mewtwo", "set": "Base", "set_num": "10/102", "lang": "EN",
      "price": 30.00, "price_date": "2026-10-22", "printing": "Holo" }
  ]
}
```

The response includes the transaction and its cards.

    Post -> HTTP 201
    Not Post -> HTTP 405
    Invalid transaction or card fields -> HTTP 400 (errors for card entries are prefixed with cards[index])
    Database error while saving -> HTTP 500

## PUT /api/transactions/update/{trans_id}

JSON body: any of *date*, *user_id*, *sold_for*. Only the fields sent are changed. Use `/api/exchanged_cards` to change the cards on a transaction.

    Put -> HTTP 200
    Not Put -> HTTP 405
    Invalid id or fields / no fields sent -> HTTP 400
    Transaction not found -> HTTP 404

## DELETE /api/transactions/delete/{trans_id}

Deletes the transaction and its `exchanged_cards` rows. The cards themselves are kept.

    Delete -> HTTP 200
    Not Delete -> HTTP 405
    Transaction not found -> HTTP 404
