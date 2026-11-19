INSERT INTO exchanged_cards (trans_id, card_id, recieved)
SELECT t.trans_id, c.card_id, (c.card_id + t.trans_id) % 2
FROM transactions t
JOIN cards c ON (c.card_id + t.trans_id) % 5 = 0
WHERE t.trans_id <= 8;